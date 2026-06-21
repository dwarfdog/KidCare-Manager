<?php

namespace App\Tests\Unit;

use App\Entity\Nanny;
use App\Entity\User;
use App\Service\CareSegmenter;
use PHPUnit\Framework\TestCase;

final class CareSegmenterTest extends TestCase
{
    public function testItSplitsOvernightCareAndKeepsMealsOnFirstSegment(): void
    {
        $user = new User();
        $nanny = (new Nanny())
            ->setFirstname('Marie')
            ->setLastname('Durand');

        $segments = (new CareSegmenter())->split(
            $user,
            $nanny,
            new \DateTimeImmutable('2026-06-20 22:30:00'),
            new \DateTimeImmutable('2026-06-21 02:15:00'),
            2,
        );

        self::assertCount(2, $segments);

        self::assertSame('2026-06-20', $segments[0]->getDate()->format('Y-m-d'));
        self::assertSame('22:30:00', $segments[0]->getStartTime()->format('H:i:s'));
        self::assertSame('00:00:00', $segments[0]->getEndTime()->format('H:i:s'));
        self::assertSame(2, $segments[0]->getMealsCount());
        self::assertSame(1.5, $segments[0]->getHoursCount());
        self::assertSame(1, $segments[0]->getSegmentIndex());
        self::assertSame(2, $segments[0]->getSegmentTotal());

        self::assertSame('2026-06-21', $segments[1]->getDate()->format('Y-m-d'));
        self::assertSame('00:00:00', $segments[1]->getStartTime()->format('H:i:s'));
        self::assertSame('02:15:00', $segments[1]->getEndTime()->format('H:i:s'));
        self::assertSame(0, $segments[1]->getMealsCount());
        self::assertSame(2.25, $segments[1]->getHoursCount());
        self::assertSame(2, $segments[1]->getSegmentIndex());
        self::assertSame(2, $segments[1]->getSegmentTotal());
        self::assertSame($segments[0]->getSegmentGroup(), $segments[1]->getSegmentGroup());
    }
}
