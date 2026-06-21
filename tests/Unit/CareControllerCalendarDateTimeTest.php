<?php

namespace App\Tests\Unit;

use App\Controller\CareController;
use App\Entity\Care;
use PHPUnit\Framework\TestCase;

final class CareControllerCalendarDateTimeTest extends TestCase
{
    public function testEventPayloadUsesFloatingLocalTimesWithoutServerTimezoneOffset(): void
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            $care = (new Care())
                ->setPublicId('01KIDCARECALENDAR000000000')
                ->setDate(new \DateTimeImmutable('2026-01-15'))
                ->setStartTime(new \DateTimeImmutable('08:00:00'))
                ->setEndTime(new \DateTimeImmutable('09:30:00'))
                ->setHoursCount(1.5);

            $payload = $this->invokePrivateMethod(new CareController(), 'eventPayload', [$care]);

            self::assertSame('2026-01-15T08:00:00', $payload['start']);
            self::assertSame('2026-01-15T09:30:00', $payload['end']);
            self::assertStringNotContainsString('+', $payload['start']);
            self::assertStringNotContainsString('Z', $payload['start']);
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    public function testPayloadDateTimeParsingAcceptsOnlyFloatingLocalTimes(): void
    {
        $controller = new CareController();

        $dateTime = $this->invokePrivateMethod($controller, 'localDateTimeFromPayload', [[
            'start' => '2026-01-15T08:00:00',
        ], 'start']);

        self::assertInstanceOf(\DateTimeImmutable::class, $dateTime);
        self::assertSame('2026-01-15 08:00:00', $dateTime->format('Y-m-d H:i:s'));

        $this->expectException(\InvalidArgumentException::class);

        $this->invokePrivateMethod($controller, 'localDateTimeFromPayload', [[
            'start' => '2026-01-15T08:00:00+00:00',
        ], 'start']);
    }

    /**
     * @param list<mixed> $arguments
     *
     * @return mixed
     */
    private function invokePrivateMethod(object $object, string $method, array $arguments): mixed
    {
        $reflectionMethod = new \ReflectionMethod($object, $method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $arguments);
    }
}
