<?php

namespace App\Tests\Unit;

use App\Entity\BankCategory;
use App\Model\BankTransactionListCriteria;
use PHPUnit\Framework\TestCase;

final class BankTransactionListCriteriaTest extends TestCase
{
    public function testItSerializesOnlyTheStableListContext(): void
    {
        $category = new BankCategory();
        $criteria = new BankTransactionListCriteria(
            3,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-06-30'),
            $category,
            'expense',
            false,
            'amount',
            'asc',
        );

        self::assertSame([
            'real_page' => 3,
            'real_from' => '2026-01-01',
            'real_to' => '2026-06-30',
            'real_category' => $category->getPublicId(),
            'real_type' => 'expense',
            'real_verified' => 'unverified',
            'real_sort' => 'amount',
            'real_direction' => 'asc',
        ], $criteria->toQuery('real'));
    }
}
