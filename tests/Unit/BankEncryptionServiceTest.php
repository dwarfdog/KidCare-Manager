<?php

namespace App\Tests\Unit;

use App\Service\BankEncryptionService;
use PHPUnit\Framework\TestCase;

final class BankEncryptionServiceTest extends TestCase
{
    public function testItEncryptsAndDecryptsSensitiveValues(): void
    {
        $service = new BankEncryptionService(base64_encode(str_repeat('a', 32)));

        $encrypted = $service->encrypt('Compte foyer');

        self::assertIsString($encrypted);
        self::assertStringStartsWith('v1:', $encrypted);
        self::assertNotSame('Compte foyer', $encrypted);
        self::assertSame('Compte foyer', $service->decrypt($encrypted));
    }

    public function testItRejectsInvalidKey(): void
    {
        $this->expectException(\RuntimeException::class);

        new BankEncryptionService('invalid');
    }
}
