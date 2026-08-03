<?php

namespace App\Service;

use App\Entity\BankAlertSetting;
use App\Entity\User;
use App\Repository\BankAccountRepository;
use App\Repository\BankAlertSettingRepository;
use App\Repository\BankBudgetRepository;
use App\Repository\BankRecurringRuleRepository;
use App\Repository\BankTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BankAlertService
{
    public function __construct(private readonly BankAlertSettingRepository $settings, private readonly BankAccountRepository $accounts, private readonly BankAccountManager $accountManager, private readonly BankBudgetCalculator $budgets, private readonly BankRecurringRuleRepository $rules, private readonly BankTransactionRepository $transactions, private readonly BankRecurringSchedule $schedule, private readonly BankEncryptionService $encryption, private readonly EntityManagerInterface $em) {}
    public function ensureDefaults(User $user): void
    {
        if ($this->settings->findAllForUser($user) !== []) return;
        foreach ([[BankAlertSetting::LOW_BALANCE, 0], [BankAlertSetting::BUDGET_RATE, 80], [BankAlertSetting::BUDGET_OVER, null], [BankAlertSetting::RECURRING_UPCOMING, 3], [BankAlertSetting::RECURRING_MISSING, 2]] as [$type, $threshold]) {
            $s = (new BankAlertSetting())->setUser($user)->setType($type)->setTargetType('global')->setThreshold($threshold);
            $this->em->persist($s);
        }
    }
    /** @return array<int,array{key:string,kind:string,title:string,message:string,link:string}> */ public function evaluate(User $user): array
    {
        $this->ensureDefaults($user);
        $out = [];
        $today = new \DateTimeImmutable('today');
        foreach ($this->settings->findActiveForUser($user) as $s) {
            if ($s->getType() === BankAlertSetting::LOW_BALANCE) {
                foreach ($this->accounts->findActiveForUser($user) as $a) {
                    if ($a->isLoan() || ($s->getTargetType() === 'account' && $s->getTargetPublicId() !== $a->getPublicId())) continue;
                    $balance = $this->accountManager->balance($a);
                    if ($balance <= ($s->getThreshold() ?? 0)) $out[] = ['key' => 'bank_low:' . $s->getPublicId() . ':' . $a->getPublicId() . ':' . $today->format('Y-m-d'), 'kind' => 'warning', 'title' => 'Solde faible', 'message' => sprintf('%s atteint %.2f €.', $this->accountManager->displayName($a), $balance), 'link' => '/bank/accounts/' . $a->getPublicId()];
                }
            }
            if (in_array($s->getType(), [BankAlertSetting::BUDGET_RATE, BankAlertSetting::BUDGET_OVER], true)) {
                foreach ($this->budgets->rows($user) as $row) {
                    $b = $row['budget'];
                    if ($s->getTargetType() === 'budget' && $s->getTargetPublicId() !== $b->getPublicId()) continue;
                    $trigger = $s->getType() === BankAlertSetting::BUDGET_OVER ? $row['rate'] > 100 : $row['rate'] >= ($s->getThreshold() ?? 80);
                    if ($trigger) $out[] = ['key' => 'bank_budget:' . $s->getPublicId() . ':' . $b->getPublicId() . ':' . $today->format('Y-m-d'), 'kind' => 'warning', 'title' => 'Budget à surveiller', 'message' => sprintf('%s est consommé à %.1f %%.', $b->getCategory()?->getName(), $row['rate']), 'link' => '/bank/budgets'];
                }
            }
            if (in_array($s->getType(), [BankAlertSetting::RECURRING_UPCOMING, BankAlertSetting::RECURRING_MISSING], true)) {
                foreach ($this->rules->findActiveForUser($user) as $r) {
                    if ($s->getTargetType() === 'recurring' && $s->getTargetPublicId() !== $r->getPublicId()) continue;
                    $next = \DateTimeImmutable::createFromInterface($r->getStartDate());
                    while ($next < $today) $next = $this->schedule->nextOccurrence($r, $next);
                    $label = $this->encryption->decrypt($r->getEncryptedLabel());
                    if ($s->getType() === BankAlertSetting::RECURRING_UPCOMING && $next <= $today->modify('+' . (int)($s->getThreshold() ?? 3) . ' days')) $out[] = ['key' => 'bank_recurring_upcoming:' . $s->getPublicId() . ':' . $r->getPublicId() . ':' . $next->format('Y-m-d'), 'kind' => 'information', 'title' => 'Récurrence prochaine', 'message' => sprintf('%s (%.2f €) arrive le %s.', $label !== '' ? $label : 'Une opération récurrente', $r->getAmount(), $next->format('d/m/Y')), 'link' => '/bank/recurring'];
                    $expected = $next > $today ? $this->previousOccurrence($r, $next) : $next;
                    if ($s->getType() === BankAlertSetting::RECURRING_MISSING && $expected <= $today->modify('-' . (int)($s->getThreshold() ?? 2) . ' days') && !$this->transactions->findOneByRuleAndDate($user, $r, $expected)) $out[] = ['key' => 'bank_recurring_missing:' . $s->getPublicId() . ':' . $r->getPublicId() . ':' . $expected->format('Y-m-d'), 'kind' => 'warning', 'title' => 'Récurrence attendue absente', 'message' => sprintf('Aucune opération trouvée pour « %s », échéance du %s.', $label !== '' ? $label : 'cette récurrence', $expected->format('d/m/Y')), 'link' => '/bank/recurring'];
                }
            }
        }
        return $out;
    }
    private function previousOccurrence($rule, \DateTimeImmutable $next): \DateTimeImmutable
    {
        $cursor = \DateTimeImmutable::createFromInterface($rule->getStartDate());
        $previous = $cursor;
        while ($cursor < $next) {
            $previous = $cursor;
            $cursor = $this->schedule->nextOccurrence($rule, $cursor);
        }
        return $previous;
    }
}
