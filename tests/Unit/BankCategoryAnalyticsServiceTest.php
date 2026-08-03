<?php

namespace App\Tests\Unit;

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Entity\User;
use App\Repository\BankTransactionRepository;
use App\Repository\BankTransactionSplitRepository;
use App\Entity\BankTransaction;
use App\Service\BankCategoryAnalyticsService;
use PHPUnit\Framework\TestCase;

final class BankCategoryAnalyticsServiceTest extends TestCase
{
    public function testEvolutionFillsEmptyMonthsAndBuildsThreeSeries(): void
    {
        $user = new User();
        $category = new BankCategory();
        $account = new BankAccount();
        $repository = $this->createMock(BankTransactionRepository::class);
        $transactions = [
            (new BankTransaction())->setUser($user)->setAccount($account)->setCategory($category)->setDate(new \DateTime('2026-01-02'))->setAmount(100),
            (new BankTransaction())->setUser($user)->setAccount($account)->setCategory($category)->setDate(new \DateTime('2026-01-03'))->setAmount(-20),
            (new BankTransaction())->setUser($user)->setAccount($account)->setCategory($category)->setDate(new \DateTime('2026-03-02'))->setAmount(5),
            (new BankTransaction())->setUser($user)->setAccount($account)->setCategory($category)->setDate(new \DateTime('2026-03-03'))->setAmount(-30),
        ];
        $repository->expects(self::once())->method('findForPeriod')->willReturn($transactions);
        $splitRepository = $this->createMock(BankTransactionSplitRepository::class);
        $splitRepository->method('findForTransaction')->willReturn([]);

        $result = (new BankCategoryAnalyticsService($repository, $splitRepository))->evolution(
            $user,
            $category,
            [$account],
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-04-30'),
        );

        self::assertTrue($result['hasData']);
        self::assertSame(['01/2026', '02/2026', '03/2026', '04/2026'], $result['labels']);
        self::assertSame([100.0, 0.0, 5.0, 0.0], $result['gains']);
        self::assertSame([20.0, 0.0, 30.0, 0.0], $result['expenses']);
        self::assertSame([80.0, 0.0, -25.0, 0.0], $result['balance']);
    }
}
