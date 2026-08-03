<?php

namespace App\Service;

use App\Entity\BankCategoryRule;
use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankCategoryRuleRepository;

final class BankCategoryRuleMatcher
{
    public function __construct(private readonly BankCategoryRuleRepository $rules, private readonly BankEncryptionService $encryption) {}
    public function apply(User $user, BankTransaction $transaction): ?BankCategoryRule
    {
        if ($transaction->getCategory() !== null) return null;
        $label = $this->encryption->decrypt($transaction->getEncryptedLabel());
        $matches = [];
        foreach ($this->rules->findActiveForUser($user) as $rule) {
            if ($rule->getAccount() !== null && $rule->getAccount() !== $transaction->getAccount()) continue;
            $amount = $transaction->getAbsoluteAmount();
            if ($rule->getMinimumAmount() !== null && $amount < $rule->getMinimumAmount()) continue;
            if ($rule->getMaximumAmount() !== null && $amount > $rule->getMaximumAmount()) continue;
            if ($this->matches($rule, $label)) $matches[] = $rule;
        }
        usort($matches, static function (BankCategoryRule $a, BankCategoryRule $b): int {
            $specific = ($b->getAccount() !== null ? 1 : 0) <=> ($a->getAccount() !== null ? 1 : 0);
            if ($specific !== 0) return $specific;
            $typeRank = [BankCategoryRule::MATCH_EXACT => 4, BankCategoryRule::MATCH_STARTS => 3, BankCategoryRule::MATCH_CONTAINS => 2, BankCategoryRule::MATCH_REGEX => 1];
            $rank = ($typeRank[$b->getMatchType()] ?? 0) <=> ($typeRank[$a->getMatchType()] ?? 0);
            return $rank !== 0 ? $rank : $b->getPriority() <=> $a->getPriority();
        });
        $winner = $matches[0] ?? null;
        if ($winner) $transaction->setCategory($winner->getCategory());
        return $winner;
    }
    private function matches(BankCategoryRule $rule, string $label): bool
    {
        $pattern = $rule->getPattern();
        $normalized = mb_strtolower($label);
        $needle = mb_strtolower($pattern);
        return match ($rule->getMatchType()) {
            BankCategoryRule::MATCH_EXACT => $normalized === $needle,
            BankCategoryRule::MATCH_STARTS => str_starts_with($normalized, $needle),
            BankCategoryRule::MATCH_REGEX => $this->regex($pattern, $label),
            default => str_contains($normalized, $needle)
        };
    }
    private function regex(string $pattern, string $label): bool
    {
        set_error_handler(static fn() => true);
        try {
            $result = preg_match($pattern, $label) === 1;
        } finally {
            restore_error_handler();
        }
        return $result;
    }
}
