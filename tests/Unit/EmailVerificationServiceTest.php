<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Service\EmailVerificationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmailVerificationServiceTest extends TestCase
{
    public function testGracePeriodUsesLatestVerificationRequestWhenAvailable(): void
    {
        $service = new EmailVerificationService(
            $this->createMock(MailerInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            true,
            'smtp://localhost',
            'no-reply@kidcare.local',
            15,
        );

        $user = (new User())
            ->setUsername('parent')
            ->setEmailVerified(false)
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01 08:00:00'))
            ->setEmailVerificationRequestedAt(new \DateTimeImmutable('2026-01-10 08:00:00'));

        self::assertFalse($service->graceExpired($user, new \DateTimeImmutable('2026-01-24 08:00:00')));
        self::assertTrue($service->graceExpired($user, new \DateTimeImmutable('2026-01-26 08:00:00')));
    }

    public function testConfigurationRejectsGracePeriodAboveFifteenDaysWhenVerificationIsRequired(): void
    {
        $service = new EmailVerificationService(
            $this->createMock(MailerInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            true,
            'smtp://localhost',
            'no-reply@kidcare.local',
            16,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('EMAIL_VERIFICATION_GRACE_DAYS depasse 15 jours');

        $service->assertConfigured();
    }
}
