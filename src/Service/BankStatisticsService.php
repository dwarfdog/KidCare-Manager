<?php

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\BankRecurringRule;
use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankAccountRepository;
use App\Repository\BankRecurringRuleRepository;
use App\Repository\BankTransactionRepository;
use App\Repository\BankTransactionSplitRepository;

final class BankStatisticsService
{
    public function __construct(
        private readonly BankAccountRepository $accountRepository,
        private readonly BankAccountManager $accountManager,
        private readonly BankTransactionRepository $transactionRepository,
        private readonly BankRecurringRuleRepository $recurringRuleRepository,
        private readonly BankRecurringSchedule $schedule,
        private readonly BankTransactionSplitRepository $splitRepository,
    ) {
    }

    public function dashboard(User $user): array
    {
        $today = new \DateTimeImmutable('today');
        $monthStart = $today->modify('first day of this month');
        $monthEnd = $today->modify('last day of this month');

        $accounts = $this->accountRepository->findActiveForUser($user);
        $currentAccounts = array_values(array_filter($accounts, fn (BankAccount $a): bool => $this->accountManager->countsForSpendableBalance($a)));

        // Solde reel: comptes courants uniquement, comme le reste a vivre et les projections.
        $spendableBalance = 0.0;
        $loansRemaining = 0.0;
        foreach ($accounts as $account) {
            if ($account->isLoan()) {
                $loansRemaining += $account->getLoanRemainingCapital() ?? 0.0;
            }
        }
        foreach ($currentAccounts as $account) {
            $spendableBalance += $this->accountManager->balance($account);
        }

        // Bilan du mois (reel + prevu) pour une lecture mensuelle complete.
        $monthTransactions = $this->transactionRepository->findForPeriod($user, $monthStart, $monthEnd, false);
        $month = $this->summarize($monthTransactions);

        $nextSalaryDate = $this->nextSalaryDate($user, $today);
        // La periode de vie s'arrete la veille du prochain salaire: tout ce qui tombe le jour
        // de reference (salaire, mais aussi prelevements du meme jour) appartient a la periode suivante.
        $periodEnd = $nextSalaryDate?->modify('-1 day');
        $plannedUntilSalary = $this->plannedNetForAccounts($currentAccounts, $today->modify('+1 day'), $periodEnd ?? $monthEnd, true);
        $plannedUntilMonthEnd = $this->plannedNetForAccounts($currentAccounts, $today->modify('+1 day'), $monthEnd);
        $accountRows = $this->accountBreakdown($accounts, $monthStart, $monthEnd, $today, $periodEnd);
        $currentRows = array_values(array_filter($accountRows, static fn (array $row): bool => (bool) $row['is_current']));
        $otherRows = array_values(array_filter($accountRows, static fn (array $row): bool => !(bool) $row['is_current']));

        return [
            'today' => $today,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'real_balance' => round($spendableBalance, 2),
            'spendable_balance' => round($spendableBalance, 2),
            'next_salary_date' => $nextSalaryDate,
            'reference_date' => $nextSalaryDate,
            'period_end' => $periodEnd,
            'reste_a_vivre' => round($spendableBalance + $plannedUntilSalary, 2),
            'projected_month_end' => round($spendableBalance + $plannedUntilMonthEnd, 2),
            'income_month' => $month['income'],
            'expense_month' => $month['expense'],
            'available_month' => round($month['income'] - $month['expense'], 2),
            'category_expenses' => $month['category_expenses'],
            'category_expense_rows' => $this->groupTopCategories($month['category_expense_rows'], 7),
            'loans_remaining' => round($loansRemaining, 2),
            'accounts' => $accountRows,
            'current_accounts' => $currentRows,
            'other_accounts' => $otherRows,
        ];
    }

    /**
     * @param BankAccount[] $accounts
     *
     * @return array<int, array<string, mixed>>
     */
    private function accountBreakdown(array $accounts, \DateTimeInterface $monthStart, \DateTimeInterface $monthEnd, \DateTimeImmutable $today, ?\DateTimeInterface $periodEnd): array
    {
        $rows = [];
        foreach ($accounts as $account) {
            $transactions = $this->transactionRepository->findForAccountPeriod($account, $monthStart, $monthEnd);
            $summary = $this->summarize($transactions);
            $balance = $this->accountManager->balance($account);
            $plannedUpcoming = $this->plannedNetForAccounts([$account], $today->modify('+1 day'), $monthEnd);
            $plannedUntilReference = $periodEnd !== null
                ? $this->plannedNetForAccounts([$account], $today->modify('+1 day'), $periodEnd, true)
                : null;

            $rows[] = [
                'account' => $account,
                'name' => $this->accountManager->displayName($account),
                'type' => $account->getType(),
                'type_label' => $this->accountManager->typeLabel($account),
                'is_loan' => $account->isLoan(),
                'is_current' => $this->accountManager->countsForSpendableBalance($account),
                'balance' => round($balance, 2),
                'income_month' => $summary['income'],
                'expense_month' => $summary['expense'],
                'planned_until_reference' => $plannedUntilReference,
                'projected_reference_balance' => $plannedUntilReference !== null ? round($balance + $plannedUntilReference, 2) : null,
                'projected_month_end' => round($balance + $plannedUpcoming, 2),
                'loan_remaining' => $account->isLoan() ? ($account->getLoanRemainingCapital() ?? 0.0) : null,
            ];
        }

        return $rows;
    }

    /** @param BankTransaction[] $transactions */
    public function summarize(array $transactions): array
    {
        $income = 0.0;
        $expense = 0.0;
        $categoryExpenses = [];
        $categoryColors = [];

        foreach ($transactions as $transaction) {
            if (in_array($transaction->getType(), [BankTransaction::TYPE_TRANSFER_IN, BankTransaction::TYPE_TRANSFER_OUT], true)) {
                continue;
            }

            $splits = $this->splitRepository->findForTransaction($transaction);
            if ($splits !== []) {
                foreach ($splits as $split) {
                    if ($split->getAmount() >= 0) { $income += $split->getAmount(); continue; }
                    $expense += abs($split->getAmount());
                    $categoryName = $split->getCategory()?->getName() ?? 'Sans categorie';
                    $categoryExpenses[$categoryName] = ($categoryExpenses[$categoryName] ?? 0.0) + abs($split->getAmount());
                    $categoryColors[$categoryName] = $split->getCategory()?->getColor() ?? '#64748b';
                }
            } elseif ($transaction->getAmount() >= 0) {
                $income += $transaction->getAmount();
            } else {
                $expense += abs($transaction->getAmount());
                $categoryName = $transaction->getCategory()?->getName() ?? 'Sans categorie';
                $categoryExpenses[$categoryName] = ($categoryExpenses[$categoryName] ?? 0.0) + abs($transaction->getAmount());
                $categoryColors[$categoryName] = $transaction->getCategory()?->getColor() ?? '#64748b';
            }
        }

        arsort($categoryExpenses);

        $rows = [];
        foreach ($categoryExpenses as $name => $value) {
            $rows[] = ['label' => $name, 'amount' => round($value, 2), 'color' => $categoryColors[$name]];
        }

        return [
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'category_expenses' => array_map(static fn (float $value): float => round($value, 2), $categoryExpenses),
            'category_expense_rows' => $rows,
        ];
    }

    /**
     * Limite le nombre de parts du graphique: au-dela de $limit categories,
     * les plus petites sont regroupees dans une part "Autres".
     *
     * @param array<int, array{label: string, amount: float, color: string}> $rows
     *
     * @return array<int, array{label: string, amount: float, color: string}>
     */
    private function groupTopCategories(array $rows, int $limit): array
    {
        if (count($rows) <= $limit + 1) {
            return $rows;
        }

        $top = array_slice($rows, 0, $limit);
        $othersAmount = 0.0;
        foreach (array_slice($rows, $limit) as $row) {
            $othersAmount += $row['amount'];
        }
        $top[] = ['label' => 'Autres', 'amount' => round($othersAmount, 2), 'color' => '#94a3b8'];

        return $top;
    }

    /**
     * Somme nette des operations prevues (planned) sur les comptes donnes, dans la fenetre [from, to].
     * Avec $excludeNextPeriodIncomes (calculs de periode de vie), les occurrences de recurrences
     * "reservees a la periode suivante" sont ignorees.
     *
     * @param BankAccount[] $accounts
     */
    private function plannedNetForAccounts(array $accounts, \DateTimeInterface $from, \DateTimeInterface $to, bool $excludeNextPeriodIncomes = false): float
    {
        if ($accounts === [] || $from > $to) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($accounts as $account) {
            foreach ($this->transactionRepository->findForAccountPeriod($account, $from, $to) as $transaction) {
                if ($transaction->getStatus() !== BankTransaction::STATUS_PLANNED) {
                    continue;
                }
                if ($excludeNextPeriodIncomes && $transaction->getRecurringRule()?->isNextPeriodAttached()) {
                    continue;
                }
                $sum += $transaction->getAmount();
            }
        }

        return round($sum, 2);
    }

    /**
     * Date du prochain salaire: prochaine occurrence d'une recurrence marquee "repere de salaire";
     * a defaut, la prochaine occurrence de revenu la plus proche.
     */
    public function nextSalaryDate(User $user, \DateTimeImmutable $today): ?\DateTimeImmutable
    {
        $rules = $this->recurringRuleRepository->findActiveForUser($user);

        $best = null;
        // Priorite aux reperes de salaire.
        foreach ($rules as $rule) {
            if (!$rule->isSalaryReference()) {
                continue;
            }
            $next = $this->nextOccurrenceAfter($rule, $today);
            if ($next !== null && ($best === null || $next < $best)) {
                $best = $next;
            }
        }
        if ($best !== null) {
            return $best;
        }

        // Repli: toute recurrence de revenu.
        foreach ($rules as $rule) {
            if (!$rule->isIncome()) {
                continue;
            }
            $next = $this->nextOccurrenceAfter($rule, $today);
            if ($next !== null && ($best === null || $next < $best)) {
                $best = $next;
            }
        }

        return $best;
    }

    private function nextOccurrenceAfter(BankRecurringRule $rule, \DateTimeImmutable $today): ?\DateTimeImmutable
    {
        $cursor = \DateTimeImmutable::createFromInterface($rule->getStartDate());
        $end = $rule->getEndDate() !== null ? \DateTimeImmutable::createFromInterface($rule->getEndDate()) : null;
        $guard = 0;

        while ($cursor <= $today) {
            $cursor = $this->schedule->nextOccurrence($rule, $cursor);
            if (++$guard > 600) {
                return null;
            }
        }

        if ($end !== null && $cursor > $end) {
            return null;
        }

        return $cursor;
    }
}
