<?php

namespace App\Tests\Unit\Course;

use App\Entity\BankRecurringRule;
use App\Entity\User;
use App\Repository\BankAccountRepository;
use App\Repository\BankRecurringRuleRepository;
use App\Repository\BankTransactionRepository;
use App\Repository\BankTransactionSplitRepository;
use App\Repository\CourseReceiptRepository;
use App\Service\BankAccountManager;
use App\Service\BankAuditLogger;
use App\Service\BankEncryptionService;
use App\Service\BankRecurringSchedule;
use App\Service\BankStatisticsService;
use App\Service\Course\CourseStatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * La periode de la depense Courses suit la periode de vie de Bank Manager (dernier salaire -> veille du
 * prochain) plutot que le mois civil, avec repli sur le mois civil si aucun repere de salaire n'est
 * configure (voir App\Service\BankStatisticsService::previousSalaryDate()/nextSalaryDate()).
 *
 * dashboard() fige "aujourd'hui" en interne (new \DateTimeImmutable('today')), donc ces tests ancrent la
 * regle de recurrence sur la date du jour reelle (today = jour d'occurrence du salaire) plutot que sur
 * une date figee, pour rester corrects quel que soit le jour d'execution.
 */
final class CourseStatisticsServiceTest extends TestCase
{
    private BankEncryptionService $encryption;

    protected function setUp(): void
    {
        $this->encryption = new BankEncryptionService(base64_encode(str_repeat('b', 32)));
    }

    public function testUsesTheSalaryPeriodBoundsWhenASalaryReferenceIsConfigured(): void
    {
        $user = new User();
        $today = new \DateTimeImmutable('today');
        // La regle de salaire "tombe" aujourd'hui : par definition, aujourd'hui ouvre la periode courante.
        $rule = (new BankRecurringRule())
            ->setStartDate($today)
            ->setFrequency(BankRecurringRule::FREQUENCY_MONTHLY)
            ->setSalaryReference(true);

        $expectedEnd = $today->modify('+1 month')->modify('-1 day');

        $receiptRepository = $this->createMock(CourseReceiptRepository::class);
        $receiptRepository->expects(self::once())->method('findConfirmedForUserPeriod')->with(
            $user,
            self::callback(static fn (\DateTimeInterface $d) => $d->format('Y-m-d') === $today->format('Y-m-d')),
            self::callback(static fn (\DateTimeInterface $d) => $d->format('Y-m-d') === $expectedEnd->format('Y-m-d')),
        )->willReturn([]);
        $receiptRepository->method('findPendingReviewForUser')->willReturn([]);
        $receiptRepository->method('findConfirmedForUser')->willReturn([]);

        $service = new CourseStatisticsService($receiptRepository, $this->encryption, $this->bankStatistics([$rule]));
        $dashboard = $service->dashboard($user);

        self::assertSame(sprintf('Periode du %s au %s', $today->format('d/m'), $expectedEnd->format('d/m')), $dashboard['periodLabel']);
    }

    public function testFallsBackToCalendarMonthWhenNoSalaryReferenceIsConfigured(): void
    {
        $user = new User();
        $today = new \DateTimeImmutable('today');
        $monthStart = $today->modify('first day of this month');
        $monthEnd = $today->modify('last day of this month');

        $receiptRepository = $this->createMock(CourseReceiptRepository::class);
        $receiptRepository->expects(self::once())->method('findConfirmedForUserPeriod')->with(
            $user,
            self::callback(static fn (\DateTimeInterface $d) => $d->format('Y-m-d') === $monthStart->format('Y-m-d')),
            self::callback(static fn (\DateTimeInterface $d) => $d->format('Y-m-d') === $monthEnd->format('Y-m-d')),
        )->willReturn([]);
        $receiptRepository->method('findPendingReviewForUser')->willReturn([]);
        $receiptRepository->method('findConfirmedForUser')->willReturn([]);

        $service = new CourseStatisticsService($receiptRepository, $this->encryption, $this->bankStatistics([]));
        $dashboard = $service->dashboard($user);

        self::assertSame('Ce mois-ci', $dashboard['periodLabel']);
    }

    /** @param BankRecurringRule[] $rules */
    private function bankStatistics(array $rules): BankStatisticsService
    {
        $recurringRuleRepository = $this->createMock(BankRecurringRuleRepository::class);
        $recurringRuleRepository->method('findActiveForUser')->willReturn($rules);

        $auditLogger = new BankAuditLogger($this->createMock(EntityManagerInterface::class), new NullLogger());
        $accountManager = new BankAccountManager(
            $this->encryption,
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
