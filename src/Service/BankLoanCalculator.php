<?php

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\User;
use App\Repository\BankAccountRepository;

final class BankLoanCalculator
{
    public function __construct(
        private readonly BankAccountRepository $accountRepository,
        private readonly BankAccountManager $accountManager,
    ) {
    }

    /**
     * @return array{
     *     loans: array<int, array{account: BankAccount, name: string, remaining: float, initial: ?float, monthly: ?float, rate: ?float, months_left: ?int, last_due_date: ?\DateTimeImmutable, progress: float}>,
     *     total_remaining: float,
     *     total_initial: float,
     *     total_monthly: float,
     *     last_due_date: ?\DateTimeImmutable
     * }
     */
    public function overview(User $user): array
    {
        $loans = [];
        $totalRemaining = 0.0;
        $totalInitial = 0.0;
        $totalMonthly = 0.0;
        $lastDue = null;

        foreach ($this->accountRepository->findActiveForUser($user) as $account) {
            if (!$account->isLoan()) {
                continue;
            }

            $row = $this->describe($account);
            $loans[] = $row;
            $totalRemaining += $row['remaining'];
            $totalInitial += $row['initial'] ?? 0.0;
            $totalMonthly += $row['monthly'] ?? 0.0;

            if ($row['last_due_date'] !== null && ($lastDue === null || $row['last_due_date'] > $lastDue)) {
                $lastDue = $row['last_due_date'];
            }
        }

        return [
            'loans' => $loans,
            'total_remaining' => round($totalRemaining, 2),
            'total_initial' => round($totalInitial, 2),
            'total_monthly' => round($totalMonthly, 2),
            'last_due_date' => $lastDue,
        ];
    }

    /**
     * @return array{account: BankAccount, name: string, remaining: float, initial: ?float, monthly: ?float, rate: ?float, payment_day: ?int, debit_account: ?BankAccount, months_left: ?int, last_payment: ?float, next_payment_date: ?\DateTimeImmutable, last_due_date: ?\DateTimeImmutable, progress: float}
     */
    public function describe(BankAccount $account): array
    {
        $remaining = $account->getLoanRemainingCapital() ?? 0.0;
        $initial = $account->getLoanInitialCapital();
        $monthly = $account->getLoanMonthlyPayment();
        $paymentDay = $account->getLoanPaymentDay();

        $monthsLeft = null;
        $lastPayment = null;
        $lastDue = null;
        $nextPayment = null;

        if ($monthly !== null && $monthly > 0 && $remaining > 0) {
            $monthsLeft = (int) ceil(round($remaining / $monthly, 6));
            // Derniere mensualite = reliquat apres les (monthsLeft - 1) mensualites pleines.
            $lastPayment = round($remaining - ($monthsLeft - 1) * $monthly, 2);
            if ($lastPayment <= 0.0) {
                $lastPayment = round($monthly, 2);
            }

            $nextPayment = $this->nextPaymentDate($paymentDay);
            if ($nextPayment !== null) {
                $lastDue = $this->occurrenceInMonth($nextPayment->modify('+' . ($monthsLeft - 1) . ' months'), $paymentDay);
            }
        }

        if ($lastDue === null && $account->getLoanEndDate() !== null) {
            $lastDue = \DateTimeImmutable::createFromInterface($account->getLoanEndDate());
        }

        $progress = 0.0;
        if ($initial !== null && $initial > 0) {
            $progress = max(0.0, min(100.0, round(($initial - $remaining) / $initial * 100, 1)));
        }

        return [
            'account' => $account,
            'name' => $this->accountManager->displayName($account),
            'remaining' => round($remaining, 2),
            'initial' => $initial,
            'monthly' => $monthly,
            'rate' => $account->getLoanAnnualRate(),
            'payment_day' => $paymentDay,
            'debit_account' => $account->getLoanDebitAccount(),
            'months_left' => $monthsLeft,
            'last_payment' => $lastPayment,
            'next_payment_date' => $nextPayment,
            'last_due_date' => $lastDue,
            'progress' => $progress,
        ];
    }

    private function nextPaymentDate(?int $paymentDay): ?\DateTimeImmutable
    {
        if ($paymentDay === null) {
            return null;
        }

        $today = new \DateTimeImmutable('today');
        $candidate = $this->occurrenceInMonth($today, $paymentDay);
        // L'echeance du jour est consideree comme traitee: la prochaine est strictement apres aujourd'hui.
        if ($candidate <= $today) {
            $candidate = $this->occurrenceInMonth($candidate->modify('first day of next month'), $paymentDay);
        }

        return $candidate;
    }

    private function occurrenceInMonth(\DateTimeImmutable $monthAnchor, ?int $day): \DateTimeImmutable
    {
        $day ??= 1;
        $lastDay = (int) $monthAnchor->format('t');
        $effectiveDay = min($day, $lastDay);

        return $monthAnchor->setDate((int) $monthAnchor->format('Y'), (int) $monthAnchor->format('n'), $effectiveDay)->setTime(0, 0);
    }
}
