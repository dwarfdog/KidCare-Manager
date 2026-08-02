<?php

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankAccountRepository;
use App\Repository\BankTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Genere les echeances mensuelles des prets (type compte "Pret") en tant que depenses
 * planifiees sur le compte courant a debiter. Le decompte du capital se fait a la
 * realisation de l'echeance (cf. BankTransactionManager::realizeDuePlanned).
 */
final class BankLoanScheduleGenerator
{
    public function __construct(
        private readonly BankAccountRepository $accountRepository,
        private readonly BankTransactionRepository $transactionRepository,
        private readonly BankEncryptionService $encryption,
        private readonly BankAccountManager $accountManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly BankAuditLogger $auditLogger,
    ) {
    }

    public function generateForUser(User $user, ?\DateTimeInterface $to = null): int
    {
        $today = new \DateTimeImmutable('today');
        $to = $to !== null ? \DateTimeImmutable::createFromInterface($to) : $today->modify('+3 months');
        $created = 0;

        foreach ($this->accountRepository->findActiveForUser($user) as $loan) {
            if (!$loan->isLoan() || !$this->isSchedulable($loan)) {
                continue;
            }
            $created += $this->generateForLoan($user, $loan, $today, $to);
        }

        return $created;
    }

    private function isSchedulable(BankAccount $loan): bool
    {
        return $loan->getLoanMonthlyPayment() !== null && $loan->getLoanMonthlyPayment() > 0
            && $loan->getLoanPaymentDay() !== null
            && $loan->getLoanDebitAccount() !== null
            && ($loan->getLoanRemainingCapital() ?? 0.0) > 0.0;
    }

    private function generateForLoan(User $user, BankAccount $loan, \DateTimeImmutable $today, \DateTimeImmutable $to): int
    {
        $monthly = (float) $loan->getLoanMonthlyPayment();
        $debit = $loan->getLoanDebitAccount();
        $name = $this->accountManager->displayName($loan);
        $label = 'Echeance pret' . ($name !== '' ? ' ' . $name : '');

        // Capital restant a planifier = capital restant - echeances deja planifiees (non encore realisees).
        $alreadyPlanned = 0.0;
        foreach ($this->transactionRepository->findPlannedForLoan($loan, $to) as $planned) {
            $alreadyPlanned += $planned->getAbsoluteAmount();
        }
        $projected = max(0.0, ($loan->getLoanRemainingCapital() ?? 0.0) - $alreadyPlanned);

        $cursor = $this->occurrenceInMonth($today, $loan->getLoanPaymentDay());
        if ($cursor < $today) {
            $cursor = $this->occurrenceInMonth($cursor->modify('first day of next month'), $loan->getLoanPaymentDay());
        }

        $created = 0;
        while ($cursor <= $to) {
            if ($projected <= 0.005) {
                break;
            }
            if ($this->transactionRepository->findOneByLoanAndDate($loan, $cursor) === null) {
                $amount = min($monthly, $projected);
                $transaction = (new BankTransaction())
                    ->setUser($user)
                    ->setAccount($debit)
                    ->setDate($cursor)
                    ->setEncryptedLabel($this->encryption->encrypt($label) ?? '')
                    ->setSignedAmount($amount, BankTransaction::TYPE_EXPENSE)
                    ->setStatus(BankTransaction::STATUS_PLANNED)
                    ->setSource(BankTransaction::SOURCE_LOAN)
                    ->setLoanAccount($loan)
                    ->setCategory($loan->getLoanCategory());
                $this->entityManager->persist($transaction);
                $this->auditLogger->record($user, 'bank.loan.generate_echeance', 'bank_transaction', $transaction->getPublicId(), [
                    'loan' => $loan->getPublicId(),
                    'date' => $cursor->format('Y-m-d'),
                    'amount' => round($amount, 2),
                ]);
                $projected -= $amount;
                ++$created;
            }

            $cursor = $this->occurrenceInMonth($cursor->modify('first day of next month'), $loan->getLoanPaymentDay());
        }

        return $created;
    }

    private function occurrenceInMonth(\DateTimeImmutable $monthAnchor, int $day): \DateTimeImmutable
    {
        $lastDay = (int) $monthAnchor->format('t');
        $effectiveDay = min($day, $lastDay);

        return $monthAnchor->setDate((int) $monthAnchor->format('Y'), (int) $monthAnchor->format('n'), $effectiveDay)->setTime(0, 0);
    }
}
