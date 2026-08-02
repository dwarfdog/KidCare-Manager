<?php

namespace App\Tests\Unit;

use App\Entity\BankRecurringRule;
use App\Service\BankRecurringSchedule;
use PHPUnit\Framework\TestCase;

final class BankRecurringScheduleTest extends TestCase
{
    public function testMonthlyOccurrencesKeepOriginalDayWhenMonthIsShorter(): void
    {
        $rule = (new BankRecurringRule())
            ->setStartDate(new \DateTimeImmutable('2026-01-31'))
            ->setFrequency(BankRecurringRule::FREQUENCY_MONTHLY);
        $schedule = new BankRecurringSchedule();

        $february = $schedule->nextOccurrence($rule, new \DateTimeImmutable('2026-01-31'));
        $march = $schedule->nextOccurrence($rule, $february);

        self::assertSame('2026-02-28', $february->format('Y-m-d'));
        self::assertSame('2026-03-31', $march->format('Y-m-d'));
    }

    public function testYearlyOccurrencesKeepLeapDayAnchorWhenPossible(): void
    {
        $rule = (new BankRecurringRule())
            ->setStartDate(new \DateTimeImmutable('2024-02-29'))
            ->setFrequency(BankRecurringRule::FREQUENCY_YEARLY);
        $schedule = new BankRecurringSchedule();

        $first = $schedule->nextOccurrence($rule, new \DateTimeImmutable('2024-02-29'));
        $second = $schedule->nextOccurrence($rule, $first);
        $third = $schedule->nextOccurrence($rule, $second);
        $fourth = $schedule->nextOccurrence($rule, $third);

        self::assertSame('2025-02-28', $first->format('Y-m-d'));
        self::assertSame('2026-02-28', $second->format('Y-m-d'));
        self::assertSame('2027-02-28', $third->format('Y-m-d'));
        self::assertSame('2028-02-29', $fourth->format('Y-m-d'));
    }

    public function testCustomDaysUsesPositiveInterval(): void
    {
        $rule = (new BankRecurringRule())
            ->setStartDate(new \DateTimeImmutable('2026-06-01'))
            ->setFrequency(BankRecurringRule::FREQUENCY_CUSTOM_DAYS)
            ->setIntervalDays(10);
        $schedule = new BankRecurringSchedule();

        self::assertSame('2026-06-11', $schedule->nextOccurrence($rule, new \DateTimeImmutable('2026-06-01'))->format('Y-m-d'));
    }
}
