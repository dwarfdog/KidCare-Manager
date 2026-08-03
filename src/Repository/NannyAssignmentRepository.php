<?php

namespace App\Repository;

use App\Entity\Nanny;
use App\Entity\NannyAssignment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NannyAssignment> */
class NannyAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NannyAssignment::class);
    }

    public function findActive(User $user, Nanny $nanny): ?NannyAssignment
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.nanny = :nanny')
            ->andWhere('a.detachedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return NannyAssignment[] */
    public function findDetachedForUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('n')
            ->innerJoin('a.nanny', 'n')
            ->andWhere('a.user = :user')
            ->andWhere('a.detachedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('a.detachedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveOrHistoricalForNanny(Nanny $nanny): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.nanny = :nanny')
            ->setParameter('nanny', $nanny)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOldestActiveForNanny(Nanny $nanny, ?User $exclude = null, ?\DateTimeInterface $activeSince = null): ?NannyAssignment
    {
        $qb = $this->createQueryBuilder('a')->andWhere('a.nanny = :nanny')->andWhere('a.detachedAt IS NULL')
            ->setParameter('nanny', $nanny)->orderBy('a.attachedAt', 'ASC')->setMaxResults(1);
        if ($exclude !== null) {
            $qb->andWhere('a.user != :excluded')->setParameter('excluded', $exclude);
        }
        if ($activeSince !== null) {
            $qb->innerJoin('a.user', 'candidate')
                ->andWhere('COALESCE(candidate.lastLoginAt, candidate.createdAt) >= :activeSince')
                ->setParameter('activeSince', $activeSince);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
