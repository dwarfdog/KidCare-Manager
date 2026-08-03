<?php

namespace App\Service;

use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BankTransactionManager
{
    public const TYPE_INTERNAL_TRANSFER = 'internal_transfer';

    public const STATUSES = [
        BankTransaction::STATUS_PLANNED => 'Prevue',
        BankTransaction::STATUS_REAL => 'Reelle',
    ];

    public const TYPES = [
        BankTransaction::TYPE_EXPENSE => 'Depense / virement externe sortant',
        BankTransaction::TYPE_INCOME => 'Revenu / virement externe entrant',
        self::TYPE_INTERNAL_TRANSFER => 'Virement interne',
    ];

    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly BankTransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly BankAuditLogger $auditLogger,
        private readonly BankCategoryRuleMatcher $ruleMatcher,
    ) {
    }

    public function save(User $user, BankTransaction $transaction, array $data): BankTransaction
    {
        $isNew = $transaction->getId() === null;
        if (!$isNew && $transaction->isReconciled()) {
            throw new \InvalidArgumentException('Dérapprochez cette opération avant de la modifier.');
        }
        $type = (string) ($data['type'] ?? BankTransaction::TYPE_EXPENSE);
        $transaction
            ->setUser($user)
            ->setAccount($data['account'])
            ->setCategory($data['category'] ?? null)
            ->setDate($data['date'] instanceof \DateTimeInterface ? $data['date'] : new \DateTime('today'))
            ->setEncryptedLabel($this->encryption->encrypt((string) ($data['label'] ?? '')) ?? '')
            ->setSignedAmount((float) ($data['amount'] ?? 0), $type)
            ->setStatus((string) ($data['status'] ?? BankTransaction::STATUS_REAL))
            ->setSource((string) ($data['source'] ?? BankTransaction::SOURCE_MANUAL));

        $this->ruleMatcher->apply($user, $transaction);

        if ($transaction->getStatus() === BankTransaction::STATUS_PLANNED) {
            $transaction->markUnverified();
        }

        $this->entityManager->persist($transaction);
        $this->auditLogger->record($user, $isNew ? 'bank.transaction.create' : 'bank.transaction.update', 'bank_transaction', $transaction->getPublicId(), [
            'amount' => $transaction->getAmount(),
            'status' => $transaction->getStatus(),
            'source' => $transaction->getSource(),
        ]);

        return $transaction;
    }

    public function setVerification(User $user, BankTransaction $transaction, bool $verified): void
    {
        if (!$transaction->isReal()) {
            throw new \InvalidArgumentException('Seules les operations realisees peuvent etre verifiees.');
        }

        $verifiedAt = $verified ? new \DateTime() : null;
        $this->applyVerification($transaction, $verifiedAt);

        $mirror = $transaction->getMirrorTransaction();
        if ($mirror !== null && $mirror->isReal()) {
            $this->applyVerification($mirror, $verifiedAt);
        }

        $this->auditLogger->record(
            $user,
            $verified ? 'bank.transaction.verify' : 'bank.transaction.unverify',
            'bank_transaction',
            $transaction->getPublicId(),
        );
    }

    public function archive(User $user, BankTransaction $transaction): void
    {
        $transaction->archive();
        if ($transaction->getMirrorTransaction() !== null && !$transaction->getMirrorTransaction()->isArchived()) {
            $transaction->getMirrorTransaction()->archive();
        }

        $this->auditLogger->record($user, 'bank.transaction.archive', 'bank_transaction', $transaction->getPublicId());
    }

    /**
     * Archive les operations planifiees (non realisees) generees par une recurrence.
     * Utilise a la desactivation: ce qui est prevu ne doit plus etre compte.
     */
    public function archivePlannedForRule(User $user, \App\Entity\BankRecurringRule $rule): int
    {
        $count = 0;
        foreach ($this->transactionRepository->findPlannedForRule($rule) as $transaction) {
            $transaction->archive();
            ++$count;
        }
        if ($count > 0) {
            $this->auditLogger->record($user, 'bank.recurring.archive_planned', 'bank_recurring_rule', $rule->getPublicId(), ['count' => $count]);
        }

        return $count;
    }

    /**
     * Filet de securite: archive les operations planifiees encore rattachees a une recurrence inactive
     * (cas des recurrences desactivees avant la mise en place de l'archivage automatique).
     */
    public function archiveOrphanedPlanned(User $user): int
    {
        $count = 0;
        foreach ($this->transactionRepository->findPlannedForInactiveRules($user) as $transaction) {
            $transaction->archive();
            ++$count;
        }

        return $count;
    }

    public function realizeDuePlanned(User $user): int
    {
        $count = 0;
        $today = new \DateTimeImmutable('today');
        foreach ($this->transactionRepository->findDuePlanned($user, $today) as $transaction) {
            if ($transaction->getStatus() !== BankTransaction::STATUS_PLANNED) {
                continue;
            }

            $rule = $transaction->getRecurringRule();
            // Securite: une echeance prevue rattachee a une recurrence desactivee ne doit jamais etre realisee.
            if ($rule !== null && !$rule->isActive()) {
                $transaction->archive();
                continue;
            }

            $transaction->setStatus(BankTransaction::STATUS_REAL);
            $mirror = $transaction->getMirrorTransaction();
            if ($mirror !== null && $mirror->getStatus() === BankTransaction::STATUS_PLANNED) {
                $mirror->setStatus(BankTransaction::STATUS_REAL);
            }

            if ($rule !== null && $rule->getLoanRemainingCapital() !== null) {
                $rule->decreaseLoanRemainingCapital($transaction->getAbsoluteAmount());
                if (!$rule->isActive()) {
                    foreach ($this->transactionRepository->findFuturePlannedForRule($rule, $today) as $futureTransaction) {
                        $futureTransaction->archive();
                    }
                }
            }

            // Echeance de pret: on decremente le capital restant du compte "Pret".
            $loan = $transaction->getLoanAccount();
            if ($loan !== null && $loan->getLoanRemainingCapital() !== null) {
                $loan->decreaseLoanRemainingCapital($transaction->getAbsoluteAmount());
                if (($loan->getLoanRemainingCapital() ?? 0.0) <= 0.0) {
                    foreach ($this->transactionRepository->findFuturePlannedForLoan($loan, $today) as $futureEcheance) {
                        $futureEcheance->archive();
                    }
                }
            }

            $this->auditLogger->record($user, 'bank.transaction.realize_due', 'bank_transaction', $transaction->getPublicId());
            ++$count;
        }

        return $count;
    }

    private function applyVerification(BankTransaction $transaction, ?\DateTimeInterface $verifiedAt): void
    {
        if ($verifiedAt !== null) {
            $transaction->markVerified($verifiedAt);

            return;
        }

        $transaction->markUnverified();
    }

    public function label(BankTransaction $transaction): string
    {
        return $this->encryption->decrypt($transaction->getEncryptedLabel());
    }

    public function deleteKidCareTransaction(User $user, BankTransaction $transaction): void
    {
        $publicId = $transaction->getPublicId();
        $this->entityManager->remove($transaction);
        $this->auditLogger->record($user, 'bank.transaction.delete_kidcare', 'bank_transaction', $publicId);
    }
}
