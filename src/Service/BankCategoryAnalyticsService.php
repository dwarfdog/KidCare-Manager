<?php

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Entity\User;
use App\Repository\BankTransactionRepository;
use App\Repository\BankTransactionSplitRepository;

final class BankCategoryAnalyticsService
{
    public function __construct(private readonly BankTransactionRepository $transactionRepository, private readonly BankTransactionSplitRepository $splitRepository)
    {
    }

    /**
     * @param BankAccount[] $accounts
     * @return array{labels: string[], gains: float[], expenses: float[], balance: float[], rows: array<int, array{month: string, gains: float, expenses: float, balance: float}>, hasData: bool}
     */
    public function evolution(User $user, BankCategory $category, array $accounts, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($from > $to) {
            return ['labels' => [], 'gains' => [], 'expenses' => [], 'balance' => [], 'rows' => [], 'hasData' => false];
        }

        $indexed = [];
        foreach ($this->transactionRepository->findForPeriod($user, $from, $to, true) as $transaction) {
            if (!in_array($transaction->getAccount(), $accounts, true)) { continue; }
            $amounts = [];
            $splits = $this->splitRepository->findForTransaction($transaction);
            if ($splits !== []) {
                foreach ($splits as $split) { if ($split->getCategory() === $category) { $amounts[] = $split->getAmount(); } }
            } elseif ($transaction->getCategory() === $category) { $amounts[] = $transaction->getAmount(); }
            foreach ($amounts as $amount) {
                $key = $transaction->getDate()->format('Y-m');
                $indexed[$key] ??= ['gains' => 0.0, 'expenses' => 0.0];
                if ($amount >= 0) { $indexed[$key]['gains'] += $amount; } else { $indexed[$key]['expenses'] += abs($amount); }
            }
        }

        $rows = [];
        $cursor = $from->modify('first day of this month');
        $lastMonth = $to->modify('first day of this month');
        $hasData = false;
        while ($cursor <= $lastMonth) {
            $key = $cursor->format('Y-m');
            $gains = (float) ($indexed[$key]['gains'] ?? 0.0);
            $expenses = (float) ($indexed[$key]['expenses'] ?? 0.0);
            $balance = round($gains - $expenses, 2);
            $hasData = $hasData || $gains !== 0.0 || $expenses !== 0.0;
            $rows[] = [
                'month' => $cursor->format('m/Y'),
                'gains' => $gains,
                'expenses' => $expenses,
                'balance' => $balance,
            ];
            $cursor = $cursor->modify('+1 month');
        }

        return [
            'labels' => array_column($rows, 'month'),
            'gains' => array_column($rows, 'gains'),
            'expenses' => array_column($rows, 'expenses'),
            'balance' => array_column($rows, 'balance'),
            'rows' => $rows,
            'hasData' => $hasData,
        ];
    }

    public function hasAnyData(User $user, BankCategory $category, array $accounts): bool
    {
        return $this->evolution($user, $category, $accounts, new \DateTimeImmutable('-20 years'), new \DateTimeImmutable('+1 day'))['hasData'];
    }
}
