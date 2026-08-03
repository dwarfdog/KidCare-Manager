<?php

namespace App\Tests\Unit;

use App\Entity\CareTemplate;
use App\Entity\Nanny;
use App\Entity\NannyAssignment;
use App\Entity\NannyRate;
use App\Entity\User;
use App\Repository\CareRepository;
use App\Repository\MonthlyPaymentRepository;
use App\Repository\NannyRateRepository;
use App\Service\ActivityLogger;
use App\Service\CareSegmenter;
use App\Service\MonthlyPaymentCalculator;
use App\Service\CareStatusSynchronizer;
use App\Service\TemplateApplicationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TemplateApplicationServiceTest extends TestCase
{
    public function testPreviewAcceptsValidSlotsAndRejectsOnlyConflictingSlots(): void
    {
        $user = (new User())->setUsername('parent');
        $nanny = (new Nanny())->setFirstname('Marie')->setLastname('Durand');
        $nanny->addAssignment((new NannyAssignment())->setUser($user));

        $template = (new CareTemplate())
            ->setUser($user)
            ->setNanny($nanny)
            ->setName('Semaine type')
            ->setWeekSchedule([
                'monday' => [
                    'isActive' => true,
                    'slots' => [['startTime' => '08:00', 'endTime' => '10:00', 'mealsCount' => 1]],
                ],
                'tuesday' => [
                    'isActive' => true,
                    'slots' => [['startTime' => '09:00', 'endTime' => '11:00', 'mealsCount' => 0]],
                ],
            ]);

        $careRepository = $this->createMock(CareRepository::class);
        $careRepository
            ->method('overlaps')
            ->willReturnCallback(static fn (User $user, Nanny $nanny, \DateTimeImmutable $start): bool => $start->format('Y-m-d') === '2026-06-23');

        $rateRepository = $this->createMock(NannyRateRepository::class);
        $rateRepository
            ->method('findApplicableRate')
            ->willReturn((new NannyRate())->setUser($user)->setNanny($nanny)->setEffectiveFrom(new \DateTimeImmutable('2026-01-01')));

        $paymentRepository = $this->createMock(MonthlyPaymentRepository::class);
        $paymentRepository->method('findOneFor')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $calculator = new MonthlyPaymentCalculator($careRepository, $paymentRepository, $rateRepository, $entityManager, new CareStatusSynchronizer($careRepository, $entityManager));
        $service = new TemplateApplicationService(
            new CareSegmenter(),
            $careRepository,
            $rateRepository,
            $calculator,
            $entityManager,
            new ActivityLogger($entityManager, new NullLogger()),
        );

        $result = $service->preview($user, $template, new \DateTimeImmutable('2026-06-22'));

        self::assertCount(2, $result['accepted']);
        self::assertCount(0, $result['rejected']);
        self::assertCount(1, $result['conflicts']);
        self::assertSame('Conflit avec une garde existante.', $result['conflicts'][0]['reason']);
    }
}
