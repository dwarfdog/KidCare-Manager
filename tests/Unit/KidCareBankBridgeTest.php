<?php

namespace App\Tests\Unit;

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Entity\BankTransaction;
use App\Entity\MonthlyPayment;
use App\Entity\User;
use App\Repository\BankCategoryRepository;
use App\Repository\BankTransactionRepository;
use App\Service\BankAuditLogger;
use App\Service\BankEncryptionService;
use App\Service\KidCareBankBridge;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class KidCareBankBridgeTest extends TestCase
{
    public function testItCreatesOneNegativeBankExpenseFromPaidKidCarePayment(): void
    {
        $user = (new User())->setUsername('parent')->setPassword('hash');
        $payment = (new MonthlyPayment())
            ->setUser($user)
            ->setNannyDisplayName('Marie Durand')
            ->setMonth('2026-06')
            ->setTotalAmount(250.75);
        $account = (new BankAccount())->setUser($user)->setEncryptedName('encrypted');
        $category = (new BankCategory())->setUser($user)->setName('Nounou');

        $transactionRepository = $this->createMock(BankTransactionRepository::class);
        $transactionRepository
            ->method('findOneForMonthlyPayment')
            ->with($user, $payment)
            ->willReturn(null);

        $categoryRepository = $this->createMock(BankCategoryRepository::class);
        $categoryRepository
            ->method('findOneByName')
            ->willReturn($category);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeastOnce())->method('persist');

        $bridge = new KidCareBankBridge(
            new BankEncryptionService(base64_encode(str_repeat('b', 32))),
            $categoryRepository,
            $transactionRepository,
            $entityManager,
            new BankAuditLogger($entityManager, new NullLogger()),
        );

        $transaction = $bridge->createExpense($user, $payment, $account, null, new \DateTimeImmutable('2026-06-30'));

        self::assertInstanceOf(BankTransaction::class, $transaction);
        self::assertSame(-250.75, $transaction->getAmount());
        self::assertSame(BankTransaction::SOURCE_KIDCARE, $transaction->getSource());
        self::assertSame($payment, $transaction->getMonthlyPayment());
    }
}
