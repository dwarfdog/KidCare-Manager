<?php

namespace App\Model;

use App\Entity\CourseProduct;
use App\Entity\CourseReceiptLine;

final class CourseParsedLine
{
    public function __construct(
        public readonly string $rawLabel,
        public readonly float $quantity = 1.0,
        public readonly string $unit = CourseProduct::UNIT_PIECE,
        public readonly ?float $unitPrice = null,
        public readonly float $lineTotal = 0.0,
        public readonly string $confidence = CourseReceiptLine::CONFIDENCE_LOW,
    ) {
    }
}
