<?php

namespace App\Service;

use App\Entity\BankRecurringRule;

final class BankRecurringSchedule
{
    public function nextOccurrence(BankRecurringRule $rule, \DateTimeImmutable $date): \DateTimeImmutable
    {
        return match ($rule->getFrequency()) {
            BankRecurringRule::FREQUENCY_WEEKLY => $date->modify('+1 week'),
            BankRecurringRule::FREQUENCY_YEARLY => $this->addYearAnchored($rule, $date),
            BankRecurringRule::FREQUENCY_CUSTOM_DAYS => $date->modify('+' . max(1, $rule->getIntervalDays() ?? 1) . ' days'),
            default => $this->addMonthAnchored($rule, $date),
        };
    }

    private function addMonthAnchored(BankRecurringRule $rule, \DateTimeImmutable $date): \DateTimeImmutable
    {
        $anchorDay = (int) $rule->getStartDate()->format('d');
        $target = $date->modify('first day of this month')->modify('+1 month');

        return $this->setClampedDay($target, $anchorDay);
    }

    private function addYearAnchored(BankRecurringRule $rule, \DateTimeImmutable $date): \DateTimeImmutable
    {
        $start = $rule->getStartDate();
        $targetYear = ((int) $date->format('Y')) + 1;
        $targetMonth = (int) $start->format('m');
        $anchorDay = (int) $start->format('d');
        $target = $date->setDate($targetYear, $targetMonth, 1);

        return $this->setClampedDay($target, $anchorDay);
    }

    private function setClampedDay(\DateTimeImmutable $date, int $day): \DateTimeImmutable
    {
        $lastDay = (int) $date->format('t');

        return $date->setDate((int) $date->format('Y'), (int) $date->format('m'), min($day, $lastDay));
    }
}
