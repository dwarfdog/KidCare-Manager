<?php

namespace App\Tests\Unit;

use App\Entity\BankRecurringRule;
use App\Repository\BankAccountRepository;
use App\Repository\BankRecurringRuleRepository;
use App\Repository\BankTransactionRepository;
use App\Repository\BankTransactionSplitRepository;
use App\Service\BankAccountManager;
use App\Service\BankAuditLogger;
use App\Service\BankEncryptionService;
use App\Service\BankRecurringSchedule;
use App\Service\BankStatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * App\Service\BankStatisticsService::previousSalaryDate() (ajoutee pour le module Courses, qui a besoin
 * de borner sa "periode de vie" comme Bank Manager). Les autres dependances du service (comptes,
 * transactions) ne sont pas utilisees par cette methode : on passe des mocks inertes.
 */
final class BankStatisticsServicePreviousSalaryDateTest extends TestCase
{
    public function testReturnsTheLastOccurrenceAtOrBeforeToday(): void
    {
        $rule = (new BankRecurringRule())
            ->setStartDate(new \DateTimeImmutable('2026-01-05'))
            ->setFrequency(BankRecurringRule::FREQUENCY_MONTHLY)
            ->setSalaryReference(true);

        $service = $this->service([$rule]);

        // Le 20 mars, le dernier salaire (mensuel, ancre au 5) etait le 5 mars.
        $result = $service->previousSalaryDate(new \App\Entity\User(), new \DateTimeImmutable('2026-03-20'));

        self::assertSame('2026-03-05', $result?->format('Y-m-d'));
    }

    public function testTodaysOccurrenceCountsAsTheStartOfTheCurrentPeriod(): void
    {
        $rule = (new BankRecurringRule())
            ->setStartDate(new \DateTimeImmutable('2026-01-05'))
            ->setFrequency(BankRecurringRule::FREQUENCY_MONTHLY)
            ->setSalaryReference(true);

        $service = $this->service([$rule]);

        $result = $service->previousSalaryDate(new \App\Entity\User(), new \DateTimeImmutable('2026-03-05'));

        self::assertSame('2026-03-05', $result?->format('Y-m-d'));
    }

    public function testReturnsNullWhenTheRuleStartsInTheFuture(): void
    {
        $rule = (new BankRecurringRule())
            ->setStartDate(new \DateTimeImmutable('2026-06-05'))
            ->setFrequency(BankRecurringRule::FREQUENCY_MONTHLY)
            ->setSalaryReference(true);

        $service = $this->service([$rule]);

        self::assertNull($service->previousSalaryDate(new \App\Entity\User(), new \DateTimeImmutable('2026-03-20')));
    }

    /** @param BankRecurringRule[] $rules */
    private function service(array $rules): BankStatisticsService
    {
        $recurringRuleRepository = $this->createMock(BankRecurringRuleRepository::class);
        $recurringRuleRepository->method('findActiveForUser')->willReturn($rules);

        $encryption = new BankEncryptionService(base64_encode(str_repeat('b', 32)));
        $auditLogger = new BankAuditLogger($this->createMock(EntityManagerInterface::class), new NullLogger());
        $accountManager = new BankAccountManager(
            $encryption,
            $this->createMock(BankTransactionRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $auditLogger,
        );

        return new BankStatisticsService(
            $this->createMock(BankAccountRepository::class),
            $accountManager,
            $this->createMock(BankTransactionRepository::class),
            $recurringRuleRepository,
            new BankRecurringSchedule(),
            $this->createMock(BankTransactionSplitRepository::class),
        );
    }
}
