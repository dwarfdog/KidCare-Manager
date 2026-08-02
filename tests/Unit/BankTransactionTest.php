<?php

namespace App\Tests\Unit;

use App\Entity\BankTransaction;
use PHPUnit\Framework\TestCase;

final class BankTransactionTest extends TestCase
{
    public function testSignedAmountsStayTransparentForUserIntent(): void
    {
        $expense = (new BankTransaction())->setSignedAmount(42.5, BankTransaction::TYPE_EXPENSE);
        $income = (new BankTransaction())->setSignedAmount(42.5, BankTransaction::TYPE_INCOME);
        $transferOut = (new BankTransaction())->setSignedAmount(42.5, BankTransaction::TYPE_TRANSFER_OUT);
        $transferIn = (new BankTransaction())->setSignedAmount(42.5, BankTransaction::TYPE_TRANSFER_IN);

        self::assertSame(-42.5, $expense->getAmount());
        self::assertSame(42.5, $income->getAmount());
        self::assertSame(-42.5, $transferOut->getAmount());
        self::assertSame(42.5, $transferIn->getAmount());
    }

    public function testVerificationIsDatedAndReversible(): void
    {
        $verifiedAt = new \DateTimeImmutable('2026-07-05 10:15:00');
        $transaction = (new BankTransaction())->markVerified($verifiedAt);

        self::assertTrue($transaction->isVerified());
        self::assertSame($verifiedAt, $transaction->getVerifiedAt());

        $transaction->markUnverified();

        self::assertFalse($transaction->isVerified());
        self::assertNull($transaction->getVerifiedAt());
    }
}
