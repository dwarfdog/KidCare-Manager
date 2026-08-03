<?php

namespace App\Model;

use App\Entity\BankTransaction;

final class BankTransactionPage
{
    /**
     * @param BankTransaction[] $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $pages,
        public readonly int $perPage,
    ) {
    }
}
