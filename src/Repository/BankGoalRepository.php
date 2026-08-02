<?php

namespace App\Repository;

use App\Entity\BankGoal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankGoal> */
class BankGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankGoal::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?BankGoal
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankGoal[] */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.status != :archived')
            ->setParameter('user', $user)
            ->setParameter('archived', BankGoal::STATUS_ARCHIVED)
            ->orderBy('g.targetDate', 'ASC')
            ->addOrderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
