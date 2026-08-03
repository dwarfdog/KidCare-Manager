<?php

namespace App\Service;

use App\Entity\Nanny;
use App\Entity\User;
use App\Repository\NannyAssignmentRepository;
use App\Repository\NannyRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NannyOwnershipService
{
    private const INACTIVITY_MONTHS = 6;

    public function __construct(
        private readonly NannyRepository $nannies,
        private readonly NannyAssignmentRepository $assignments,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function transferInactiveOwnerships(): int
    {
        $count = 0;
        $cutoff = new \DateTimeImmutable('-'.self::INACTIVITY_MONTHS.' months');
        foreach ($this->nannies->findWithInactiveOwner($cutoff) as $nanny) {
            if ($this->transferToOldestActive($nanny, $nanny->getCreatedBy(), $cutoff)) {
                ++$count;
            }
        }
        if ($count > 0) { $this->entityManager->flush(); }
        return $count;
    }

    public function transferBeforeAccountDeletion(Nanny $nanny, User $owner): bool
    {
        return $this->transferToOldestActive($nanny, $owner);
    }

    private function transferToOldestActive(Nanny $nanny, ?User $excluded, ?\DateTimeInterface $activeSince = null): bool
    {
        $assignment = $this->assignments->findOldestActiveForNanny(
            $nanny,
            $excluded,
            $activeSince,
        );
        if ($assignment?->getUser() === null) { return false; }
        $nanny->setCreatedBy($assignment->getUser())->setOwnershipTransferredAt(new \DateTime());
        return true;
    }
}
