<?php

namespace App\Service;

use App\Entity\Care;
use App\Entity\Nanny;
use App\Entity\User;
use App\Repository\CareRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CareStatusSynchronizer
{
    public function __construct(private readonly CareRepository $cares, private readonly EntityManagerInterface $entityManager) {}

    public function synchronize(User $user, ?Nanny $nanny = null): int
    {
        $changed = 0;
        foreach ($this->cares->findEndedPlannedUnlocked($user, $nanny) as $care) {
            if ($care->getEndDateTime() <= new \DateTimeImmutable()) {
                $care->setStatus(Care::STATUS_REALIZED);
                ++$changed;
            }
        }
        if ($changed > 0) {
            $this->entityManager->flush();
        }

        return $changed;
    }
}
