<?php

namespace App\Repository;

use App\Entity\CourseShoppingList;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseShoppingList> */
class CourseShoppingListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseShoppingList::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?CourseShoppingList
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return CourseShoppingList[] */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return CourseShoppingList[] */
    public function findArchivedForUser(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.archivedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('l.archivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
