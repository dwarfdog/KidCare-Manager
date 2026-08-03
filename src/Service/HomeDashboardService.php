<?php

namespace App\Service;

use App\Entity\MonthlyPayment;
use App\Entity\User;
use App\Repository\BankGoalRepository;
use App\Repository\BankTransactionRepository;
use App\Repository\CareRepository;
use App\Repository\MonthlyPaymentRepository;
use App\Repository\NannyRepository;

final class HomeDashboardService
{
    public function __construct(
        private readonly BankDashboardSynchronizer $bankSynchronizer,
        private readonly BankStatisticsService $bankStatistics,
        private readonly BankTransactionManager $transactionManager,
        private readonly BankAccountManager $accountManager,
        private readonly BankTransactionRepository $transactionRepository,
        private readonly BankBudgetCalculator $budgetCalculator,
        private readonly BankGoalRepository $goalRepository,
        private readonly BankGoalCalculator $goalCalculator,
        private readonly MonthlyPaymentRepository $paymentRepository,
        private readonly CareRepository $careRepository,
        private readonly NannyRepository $nannyRepository,
        private readonly ApplicationSettings $settings,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $this->bankSynchronizer->synchronize($user);
        $payments = $this->paymentRepository->findCurrentMonth($user);
        $paidCount = count(array_filter($payments, static fn (MonthlyPayment $payment): bool => $payment->isPaid()));
        $totalHours = array_reduce($payments, static fn (float $sum, MonthlyPayment $payment): float => $sum + $payment->getTotalsHours(), 0.0);
        $totalAmount = array_reduce($payments, static fn (float $sum, MonthlyPayment $payment): float => $sum + $payment->getTotalAmount(), 0.0);
        $budgetRows = $this->budgetCalculator->rows($user);
        $attentionBudgets = array_values(array_filter($budgetRows, static fn (array $row): bool => in_array($row['status'], ['risk', 'over'], true)));
        $goals = $this->goalRepository->findActiveForUser($user);

        return [
            'bank' => $this->bankStatistics->dashboard($user),
            'recentTransactions' => $this->transactionRepository->findRecentForUser($user, 6),
            'budgetRows' => array_slice($budgetRows, 0, 4),
            'attentionBudgets' => array_slice($attentionBudgets, 0, 4),
            'goalRows' => array_slice($this->goalCalculator->rows($goals), 0, 4),
            'currentPayments' => $payments,
            'recentPayments' => $this->paymentRepository->findRecentBeforeCurrentMonth($user),
            'paymentSummary' => [
                'count' => count($payments),
                'paid' => $paidCount,
                'pending' => count($payments) - $paidCount,
                'hours' => round($totalHours, 2),
                'amount' => round($totalAmount, 2),
            ],
            'nextCare' => $this->careRepository->findNextCare($user),
            'nannyCount' => count($this->nannyRepository->findAssignedToUser($user)),
            'currency' => $this->settings->currency(),
            'transactionManager' => $this->transactionManager,
            'accountManager' => $this->accountManager,
        ];
    }
}
