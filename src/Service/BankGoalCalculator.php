<?php

namespace App\Service;

use App\Entity\BankGoal;

/**
 * Evalue les objectifs financiers.
 *
 * Le montant actuel d'un objectif lie a un compte suit automatiquement le solde
 * de ce compte; un objectif non lie s'alimente manuellement. Le statut "Atteint"
 * est synchronise automatiquement avec le montant effectif.
 */
final class BankGoalCalculator
{
    public function __construct(private readonly BankAccountManager $accountManager)
    {
    }

    /**
     * @param BankGoal[] $goals
     *
     * @return array<int, array{
     *     goal: BankGoal,
     *     current: float,
     *     rate: float,
     *     remaining: float,
     *     achieved: bool,
     *     monthly_needed: ?float,
     *     target_date_passed: bool,
     * }>
     */
    public function rows(array $goals, ?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');
        $rows = [];

        foreach ($goals as $goal) {
            $current = $this->currentAmount($goal);
            $target = $goal->getTargetAmount();
            $achieved = $target > 0 && $current >= $target;
            $targetDate = $goal->getTargetDate() !== null ? \DateTimeImmutable::createFromInterface($goal->getTargetDate()) : null;

            $rows[] = [
                'goal' => $goal,
                'current' => round($current, 2),
                'rate' => $target > 0 ? min(100.0, round($current / $target * 100, 1)) : 0.0,
                'remaining' => round(max(0.0, $target - $current), 2),
                'achieved' => $achieved,
                'monthly_needed' => $this->monthlyNeeded($goal, $current, $today),
                'target_date_passed' => !$achieved && $targetDate !== null && $targetDate < $today,
            ];
        }

        return $rows;
    }

    /** Montant effectif: solde du compte lie, sinon montant saisi manuellement. */
    public function currentAmount(BankGoal $goal): float
    {
        if ($goal->getLinkedAccount() !== null) {
            return $this->accountManager->balance($goal->getLinkedAccount());
        }

        return $goal->getCurrentAmount();
    }

    /**
     * Synchronise le statut Actif/Atteint avec le montant effectif.
     * Retourne true si au moins un statut a change (flush a la charge de l'appelant).
     *
     * @param BankGoal[] $goals
     */
    public function syncStatuses(array $goals): bool
    {
        $changed = false;
        foreach ($goals as $goal) {
            if ($goal->getStatus() === BankGoal::STATUS_ARCHIVED) {
                continue;
            }
            $achieved = $goal->getTargetAmount() > 0 && $this->currentAmount($goal) >= $goal->getTargetAmount();
            $expected = $achieved ? BankGoal::STATUS_ACHIEVED : BankGoal::STATUS_ACTIVE;
            if ($goal->getStatus() !== $expected) {
                $goal->setStatus($expected);
                $changed = true;
            }
        }

        return $changed;
    }

    /** Epargne mensuelle necessaire pour atteindre la cible a la date cible. */
    private function monthlyNeeded(BankGoal $goal, float $current, \DateTimeImmutable $today): ?float
    {
        if ($goal->getTargetDate() === null) {
            return null;
        }

        $remaining = $goal->getTargetAmount() - $current;
        if ($remaining <= 0) {
            return null;
        }

        $targetDate = \DateTimeImmutable::createFromInterface($goal->getTargetDate());
        if ($targetDate <= $today) {
            return null;
        }

        $diff = $today->diff($targetDate);
        $months = max(1, $diff->y * 12 + $diff->m + ($diff->d > 0 ? 1 : 0));

        return round($remaining / $months, 2);
    }
}
