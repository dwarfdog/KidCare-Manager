<?php

namespace App\Model;

final class CourseParsedReceipt
{
    /** @param CourseParsedLine[] $lines */
    public function __construct(
        public readonly ?string $storeNameGuess,
        public readonly ?\DateTimeImmutable $date,
        public readonly ?float $total,
        public readonly array $lines,
    ) {
    }
}
