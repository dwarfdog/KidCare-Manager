<?php

namespace App\Repository;

use App\Entity\CourseProduct;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseProduct> */
class CourseProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseProduct::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?CourseProduct
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return CourseProduct[] */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return CourseProduct[] */
    public function findArchivedForUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.archivedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('p.archivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
