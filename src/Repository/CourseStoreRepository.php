<?php

namespace App\Repository;

use App\Entity\CourseStore;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseStore> */
class CourseStoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseStore::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?CourseStore
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return CourseStore[] */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return CourseStore[] */
    public function findArchivedForUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.archivedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('s.archivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
