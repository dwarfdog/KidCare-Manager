<?php

namespace App\Service;

use App\Entity\BankRecurringException;
use App\Entity\BankSimulation;
use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankAccountRepository;
use App\Repository\BankRecurringExceptionRepository;
use App\Repository\BankRecurringRuleRepository;
use App\Repository\BankTransactionRepository;

final class BankSimulationService
{
    public function __construct(
        private readonly BankAccountRepository $accounts,
        private readonly BankAccountManager $accountManager,
        private readonly BankTransactionRepository $transactions,
        private readonly BankStatisticsService $statistics,
        private readonly BankRecurringRuleRepository $recurringRules,
        private readonly BankRecurringExceptionRepository $recurringExceptions,
        private readonly BankRecurringSchedule $recurringSchedule,
        private readonly BankEncryptionService $encryption,
    ) {
    }

    /** @return array<string, string> */
    public static function horizons(): array
    {
        return [
            'next_salary' => 'Prochaine paie',
            'month_end' => 'Fin du mois',
            '3_months' => '3 mois',
            '6_months' => '6 mois',
            '12_months' => '12 mois',
        ];
    }

    public function targetDate(User $user, string $horizon): \DateTimeImmutable
    {
        $today = new \DateTimeImmutable('today');

        return match ($horizon) {
            // La periode de vie s'arrete la veille du prochain salaire (cf. BankStatisticsService::dashboard).
            'next_salary' => $this->statistics->nextSalaryDate($user, $today)?->modify('-1 day') ?? $today->modify('last day of this month'),
            '3_months' => $today->modify('+3 months'),
            '6_months' => $today->modify('+6 months'),
            '12_months' => $today->modify('+12 months'),
            default => $today->modify('last day of this month'),
        };
    }

    /**
     * @param array<int, array{date?: mixed, label?: mixed, amount?: mixed}> $assumptions
     * @return array{targetDate: \DateTimeImmutable, startBalance: float, planned: float, recurring: float, manual: float, result: float, items: array<int, array{date: \DateTimeImmutable|\DateTimeInterface, label: string, amount: float}>}
     */
    public function calculate(User $user, string $horizon, array $assumptions = []): array
    {
        if (!array_key_exists($horizon, self::horizons())) {
            $horizon = 'month_end';
        }

        $today = new \DateTimeImmutable('today');
        $from = $today->modify('+1 day');
        $target = $this->targetDate($user, $horizon);
        $start = 0.0;
        foreach ($this->accounts->findActiveForUser($user) as $account) {
            if (!$account->isLoan()) {
                $start += $this->accountManager->balance($account);
            }
        }

        $planned = 0.0;
        $recurring = 0.0;
        $items = [];
        foreach ($this->transactions->findForPeriod($user, $from, $target, false) as $transaction) {
            if ($transaction->getStatus() !== BankTransaction::STATUS_PLANNED) {
                continue;
            }
            if ($horizon === 'next_salary' && $transaction->getRecurringRule()?->isNextPeriodAttached()) {
                continue;
            }
            $planned += $transaction->getAmount();
            $items[] = [
                'date' => $transaction->getDate(),
                'label' => $transaction->getSource() === BankTransaction::SOURCE_RECURRING ? 'Récurrence prévue' : 'Opération prévue',
                'amount' => $transaction->getAmount(),
            ];
        }

        // Recurrences are generated only a few months ahead in normal usage.
        // Project the missing occurrences without persisting synthetic transactions.
        foreach ($this->recurringRules->findActiveForUser($user) as $rule) {
            if ($horizon === 'next_salary' && $rule->isNextPeriodAttached()) {
                continue;
            }
            $cursor = \DateTimeImmutable::createFromInterface($rule->getStartDate());
            while ($cursor < $from) {
                $cursor = $this->recurringSchedule->nextOccurrence($rule, $cursor);
            }
            $exceptions = $this->recurringExceptions->findIndexedForRule($rule);
            while ($cursor <= $target) {
                if ($rule->getEndDate() !== null && $cursor > \DateTimeImmutable::createFromInterface($rule->getEndDate())) {
                    break;
                }

                $exception = $exceptions[$cursor->format('Y-m-d')] ?? null;
                $alreadyGenerated = $this->transactions->findOneByRuleAndDate($user, $rule, $cursor) !== null;
                if (!$alreadyGenerated && $exception?->getAction() !== BankRecurringException::ACTION_SKIP) {
                    $date = $exception?->getOverrideDate() !== null ? \DateTimeImmutable::createFromInterface($exception->getOverrideDate()) : $cursor;
                    if ($date >= $from && $date <= $target) {
                        $amount = $exception?->getOverrideAmount() ?? $rule->getAmount();
                        $label = $exception?->getOverrideEncryptedLabel() !== null
                            ? $this->encryption->decrypt($exception->getOverrideEncryptedLabel())
                            : $this->encryption->decrypt($rule->getEncryptedLabel());
                        $recurring += $amount;
                        $items[] = ['date' => $date, 'label' => $label.' (récurrence projetée)', 'amount' => $amount];
                    }
                }
                $cursor = $this->recurringSchedule->nextOccurrence($rule, $cursor);
            }
        }

        $manual = 0.0;
        foreach ($assumptions as $item) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($item['date'] ?? ''));
            $amount = (float) str_replace(',', '.', (string) ($item['amount'] ?? 0));
            if (!$date || $date < $today || $date > $target) {
                continue;
            }
            $manual += $amount;
            $items[] = [
                'date' => $date,
                'label' => trim((string) ($item['label'] ?? '')) ?: 'Hypothèse',
                'amount' => $amount,
            ];
        }

        usort($items, static fn (array $left, array $right): int => $left['date'] <=> $right['date']);

        return [
            'targetDate' => $target,
            'startBalance' => round($start, 2),
            'planned' => round($planned, 2),
            'recurring' => round($recurring, 2),
            'manual' => round($manual, 2),
            'result' => round($start + $planned + $recurring + $manual, 2),
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    public function fromEntity(User $user, BankSimulation $simulation): array
    {
        return $this->calculate($user, $simulation->getHorizon(), $simulation->getAssumptions());
    }
}
