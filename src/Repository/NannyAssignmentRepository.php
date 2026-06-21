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

    public function countActiveOrHistoricalForNanny(Nanny $nanny): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.nanny = :nanny')
            ->setParameter('nanny', $nanny)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
