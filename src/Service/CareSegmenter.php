<?php

namespace App\Service;

use App\Entity\Care;
use App\Entity\Nanny;
use App\Entity\User;

final class CareSegmenter
{
    /** @return Care[] */
    public function split(User $user, Nanny $nanny, \DateTimeImmutable $start, \DateTimeImmutable $end, int $meals): array
    {
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        $segments = [];
        $group = UlidGenerator::generate($start);
        $cursor = $start;
        $index = 1;

        while ($cursor < $end) {
            $midnight = $cursor->modify('tomorrow')->setTime(0, 0);
            $segmentEnd = $end < $midnight ? $end : $midnight;
            $seconds = $segmentEnd->getTimestamp() - $cursor->getTimestamp();

            if ($seconds <= 0) {
                break;
            }

            $care = new Care();
            $care
                ->setUser($user)
                ->setNanny($nanny)
                ->setNannyDisplayName($nanny->getFullName())
                ->setDate(\DateTime::createFromImmutable($cursor))
                ->setStartTime(\DateTime::createFromImmutable($cursor))
                ->setEndTime(\DateTime::createFromImmutable($segmentEnd))
                ->setMealsCount($index === 1 ? $meals : 0)
                ->setHoursCount($seconds / 3600)
                ->setSegmentGroup($group)
                ->setSegmentIndex($index);

            if ($segmentEnd <= new \DateTimeImmutable()) {
                $care->setStatus(Care::STATUS_REALIZED);
            }

            $segments[] = $care;
            $cursor = $segmentEnd;
            $index++;
        }

        $total = count($segments);
        foreach ($segments as $segment) {
            $segment->setSegmentTotal($total);
        }

        return $segments;
    }
}
