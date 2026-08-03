<?php

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\User;
use App\Repository\BankTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BankAccountManager
{
    public const TYPES = [
        BankAccount::TYPE_CURRENT => 'Compte courant',
        BankAccount::TYPE_SAVINGS => 'Livret / epargne',
        BankAccount::TYPE_DEFERRED_CARD => 'Carte differee',
        BankAccount::TYPE_CREDIT => 'Credit',
        BankAccount::TYPE_LOAN => 'Pret',
        BankAccount::TYPE_CASH => 'Especes',
    ];

    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly BankTransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly BankAuditLogger $auditLogger,
    ) {
    }

    public function save(User $user, BankAccount $account, array $data): BankAccount
    {
        $isNew = $account->getId() === null;
        $account
            ->setUser($user)
            ->setEncryptedName($this->encryption->encrypt((string) ($data['name'] ?? '')) ?? '')
            ->setEncryptedIban($this->encryption->encrypt($this->nullableString($data['iban'] ?? null)))
            ->setType((string) ($data['type'] ?? BankAccount::TYPE_CURRENT))
            ->setInitialBalance((float) ($data['initialBalance'] ?? 0))
            ->setInitialBalanceDate($data['initialBalanceDate'] instanceof \DateTimeInterface ? $data['initialBalanceDate'] : new \DateTime('today'))
            ->setLoanInitialCapital($data['loanInitialCapital'] ?? null)
            ->setLoanRemainingCapital($data['loanRemainingCapital'] ?? null)
            ->setLoanAnnualRate($data['loanAnnualRate'] ?? null)
            ->setLoanEndDate($data['loanEndDate'] instanceof \DateTimeInterface ? $data['loanEndDate'] : null)
            ->setLoanMonthlyPayment($data['loanMonthlyPayment'] ?? null)
            ->setLoanPaymentDay($data['loanPaymentDay'] ?? null)
            ->setLoanDebitAccount($data['loanDebitAccount'] ?? null)
            ->setLoanCategory($data['loanCategory'] ?? null);

        if (!$account->isLoan()) {
            $account
                ->setLoanInitialCapital(null)
                ->setLoanRemainingCapital(null)
                ->setLoanAnnualRate(null)
                ->setLoanEndDate(null)
                ->setLoanMonthlyPayment(null)
                ->setLoanPaymentDay(null)
                ->setLoanDebitAccount(null)
                ->setLoanCategory(null);
        }

        // La categorie des echeances s'applique retroactivement a toutes les echeances
        // existantes du pret (prevues et realisees), pour des statistiques coherentes.
        if (!$isNew && $account->isLoan()) {
            foreach ($this->transactionRepository->findAllForLoan($account) as $echeance) {
                $echeance->setCategory($account->getLoanCategory());
            }
        }

        $this->entityManager->persist($account);
        $this->auditLogger->record($user, $isNew ? 'bank.account.create' : 'bank.account.update', 'bank_account', $account->getPublicId(), [
            'type' => $account->getType(),
        ]);

        return $account;
    }

    public function archive(User $user, BankAccount $account): void
    {
        $account->archive();

        // Un pret archive ne doit plus generer/peser: on retire ses echeances prevues (portees par le compte a debiter).
        if ($account->isLoan()) {
            foreach ($this->transactionRepository->findPlannedForLoan($account, new \DateTimeImmutable('+30 years')) as $echeance) {
                $echeance->archive();
            }
        }

        $this->auditLogger->record($user, 'bank.account.archive', 'bank_account', $account->getPublicId());
    }

    public function typeLabel(BankAccount $account): string
    {
        return self::TYPES[$account->getType()] ?? $account->getType();
    }

    public function balance(BankAccount $account): float
    {
        return round($account->getInitialBalance() + $this->transactionRepository->sumRealForAccount($account), 2);
    }

    public function projectedBalance(BankAccount $account, ?\DateTimeInterface $referenceDate): ?float
    {
        if ($referenceDate === null) {
            return null;
        }

        return round($this->balance($account) + $this->plannedNetUntil($account, $referenceDate), 2);
    }

    public function plannedNetUntil(BankAccount $account, \DateTimeInterface $referenceDate): float
    {
        return round($this->transactionRepository->sumPlannedForAccountUntil(
            $account,
            new \DateTimeImmutable('tomorrow'),
            $referenceDate,
        ), 2);
    }

    public function countsForSpendableBalance(BankAccount $account): bool
    {
        return $account->getType() === BankAccount::TYPE_CURRENT;
    }

    public function displayName(BankAccount $account): string
    {
        return $this->encryption->decrypt($account->getEncryptedName());
    }

    public function displayIban(BankAccount $account): string
    {
        return $this->encryption->decrypt($account->getEncryptedIban());
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
