<?php

namespace App\Repository;

use App\Entity\BankBudget;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankBudget> */
class BankBudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankBudget::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?BankBudget
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->andWhere('b.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankBudget[] */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->andWhere('b.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('b.periodStart', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return BankBudget[] */
    public function findArchivedForUser(User $user): array
    {
        return $this->createQueryBuilder('b')->andWhere('b.user=:user')->andWhere('b.archivedAt IS NOT NULL')->setParameter('user', $user)->orderBy('b.archivedAt', 'DESC')->getQuery()->getResult();
    }
}
