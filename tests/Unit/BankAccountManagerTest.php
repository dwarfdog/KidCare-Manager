<?php

namespace App\Tests\Unit;

use App\Entity\BankAccount;
use App\Entity\User;
use App\Repository\BankTransactionRepository;
use App\Service\BankAccountManager;
use App\Service\BankAuditLogger;
use App\Service\BankEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class BankAccountManagerTest extends TestCase
{
    public function testSavingNewLoanDoesNotQueryExistingLoanTransactionsBeforeIdentifierExists(): void
    {
        $user = (new User())->setUsername('parent')->setPassword('hash');

        $transactionRepository = $this->createMock(BankTransactionRepository::class);
        $transactionRepository
            ->expects(self::never())
            ->method('findAllForLoan');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeastOnce())->method('persist');

        $manager = new BankAccountManager(
            new BankEncryptionService(base64_encode(str_repeat('b', 32))),
            $transactionRepository,
            $entityManager,
            new BankAuditLogger($entityManager, new NullLogger()),
        );

        $account = $manager->save($user, new BankAccount(), [
            'name' => 'Pret immobilier',
            'type' => BankAccount::TYPE_LOAN,
            'initialBalance' => 0,
            'initialBalanceDate' => new \DateTimeImmutable('2026-08-01'),
            'loanInitialCapital' => 200000,
            'loanRemainingCapital' => 200000,
            'loanEndDate' => null,
            'loanMonthlyPayment' => 950,
            'loanPaymentDay' => 5,
        ]);

        self::assertTrue($account->isLoan());
        self::assertNull($account->getId());
        self::assertSame(200000.0, $account->getLoanInitialCapital());
        self::assertSame(950.0, $account->getLoanMonthlyPayment());
    }
}
