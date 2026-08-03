<?php

namespace App\Service;

use App\Entity\BankBudget;
use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankBudgetRepository;
use App\Repository\BankTransactionRepository;
use App\Repository\BankTransactionSplitRepository;

/**
 * Evalue les budgets actifs de l'utilisateur.
 *
 * Un budget hebdomadaire/mensuel/annuel est permanent: il se reconduit automatiquement
 * et s'evalue sur la periode en cours (semaine, mois ou annee du jour). Un budget
 * personnalise s'evalue sur ses dates fixes.
 */
final class BankBudgetCalculator
{
    public const STATUS_OK = 'ok';
    public const STATUS_RISK = 'risk';
    public const STATUS_OVER = 'over';
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_ENDED = 'ended';

    public function __construct(
        private readonly BankBudgetRepository $budgetRepository,
        private readonly BankTransactionRepository $transactionRepository,
        private readonly BankTransactionSplitRepository $splitRepository,
    ) {
    }

    /**
     * @return array<int, array{
     *     budget: BankBudget,
     *     period_start: \DateTimeImmutable,
     *     period_end: \DateTimeImmutable,
     *     spent: float,
     *     remaining: float,
     *     rate: float,
     *     projected: ?float,
     *     status: string,
     * }>
     */
    public function rows(User $user, ?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');
        $spentCache = [];
        $rows = [];

        foreach ($this->budgetRepository->findActiveForUser($user) as $budget) {
            [$start, $end] = $this->currentPeriod($budget, $today);
            $spentByCategory = $this->spentByCategory($user, $start, $end, $spentCache);
            $spent = round($spentByCategory[$budget->getCategory()->getId()] ?? 0.0, 2);
            $amount = $budget->getAmount();
            $projected = $this->projected($spent, $start, $end, $today);

            $rows[] = [
                'budget' => $budget,
                'period_start' => $start,
                'period_end' => $end,
                'spent' => $spent,
                'remaining' => round($amount - $spent, 2),
                'rate' => $amount > 0 ? round($spent / $amount * 100, 1) : 0.0,
                'projected' => $projected,
                'status' => $this->status($budget, $spent, $projected, $start, $end, $today),
            ];
        }

        return $rows;
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    private function currentPeriod(BankBudget $budget, \DateTimeImmutable $today): array
    {
        return match ($budget->getPeriodType()) {
            BankBudget::PERIOD_WEEK => [$today->modify('monday this week'), $today->modify('monday this week')->modify('+6 days')],
            BankBudget::PERIOD_MONTH => [$today->modify('first day of this month'), $today->modify('last day of this month')],
            BankBudget::PERIOD_YEAR => [$today->setDate((int) $today->format('Y'), 1, 1), $today->setDate((int) $today->format('Y'), 12, 31)],
            default => [
                \DateTimeImmutable::createFromInterface($budget->getPeriodStart()),
                \DateTimeImmutable::createFromInterface($budget->getPeriodEnd()),
            ],
        };
    }

    /**
     * Depenses reelles par categorie sur la fenetre donnee, memoisees par fenetre
     * (les budgets d'un meme type partagent la meme periode).
     *
     * @param array<string, array<int, float>> $cache
     *
     * @return array<int, float>
     */
    private function spentByCategory(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to, array &$cache): array
    {
        $key = $from->format('Y-m-d') . '|' . $to->format('Y-m-d');
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $spent = [];
        foreach ($this->transactionRepository->findForPeriod($user, $from, $to, true) as $transaction) {
            $splits = $this->splitRepository->findForTransaction($transaction);
            if ($splits !== []) {
                foreach ($splits as $split) {
                    if ($split->getAmount() >= 0 || $split->getCategory() === null) { continue; }
                    $categoryId = $split->getCategory()->getId();
                    $spent[$categoryId] = ($spent[$categoryId] ?? 0.0) + abs($split->getAmount());
                }
                continue;
            }
            if (!$this->isExpenseForBudget($transaction) || $transaction->getCategory() === null) {
                continue;
            }
            $categoryId = $transaction->getCategory()->getId();
            $spent[$categoryId] = ($spent[$categoryId] ?? 0.0) + abs($transaction->getAmount());
        }

        return $cache[$key] = $spent;
    }

    /** Estimation de la depense en fin de periode si le rythme actuel se maintient. */
    private function projected(float $spent, \DateTimeImmutable $start, \DateTimeImmutable $end, \DateTimeImmutable $today): ?float
    {
        if ($today < $start || $today > $end) {
            return null;
        }

        $totalDays = (int) $start->diff($end)->days + 1;
        $elapsedDays = (int) $start->diff($today)->days + 1;
        if ($elapsedDays <= 0 || $totalDays <= 0) {
            return null;
        }

        return round($spent / $elapsedDays * $totalDays, 2);
    }

    private function status(BankBudget $budget, float $spent, ?float $projected, \DateTimeImmutable $start, \DateTimeImmutable $end, \DateTimeImmutable $today): string
    {
        if ($today < $start) {
            return self::STATUS_UPCOMING;
        }
        if ($today > $end) {
            return $spent > $budget->getAmount() ? self::STATUS_OVER : self::STATUS_ENDED;
        }
        if ($spent > $budget->getAmount()) {
            return self::STATUS_OVER;
        }
        if ($projected !== null && $projected > $budget->getAmount()) {
            return self::STATUS_RISK;
        }

        return self::STATUS_OK;
    }

    private function isExpenseForBudget(BankTransaction $transaction): bool
    {
        return $transaction->getAmount() < 0
            && !in_array($transaction->getType(), [BankTransaction::TYPE_TRANSFER_IN, BankTransaction::TYPE_TRANSFER_OUT], true);
    }
}
