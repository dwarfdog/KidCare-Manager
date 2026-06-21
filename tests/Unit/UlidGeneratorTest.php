<?php

namespace App\Tests\Unit;

use App\Service\UlidGenerator;
use PHPUnit\Framework\TestCase;

final class UlidGeneratorTest extends TestCase
{
    public function testGeneratedUlidUsesExpectedShape(): void
    {
        $ulid = UlidGenerator::generate(new \DateTimeImmutable('2026-06-20 12:00:00'));

        self::assertMatchesRegularExpression('/^[0-9ABCDEFGHJKMNPQRSTVWXYZ]{26}$/', $ulid);
    }

    public function testTimePrefixIsOrdered(): void
    {
        $first = UlidGenerator::generate(new \DateTimeImmutable('2026-06-20 12:00:00'));
        $second = UlidGenerator::generate(new \DateTimeImmutable('2026-06-20 12:00:01'));

        self::assertLessThan(substr($second, 0, 10), substr($first, 0, 10));
    }
}
