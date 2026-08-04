<?php

namespace App\Repository;

use App\Entity\CourseProductAlias;
use App\Entity\CourseStore;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseProductAlias> */
class CourseProductAliasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseProductAlias::class);
    }

    public function findOneForUserStoreAndLabel(User $user, CourseStore $store, string $normalizedLabel): ?CourseProductAlias
    {
        return $this->findOneBy(['user' => $user, 'store' => $store, 'normalizedLabel' => $normalizedLabel]);
    }
}
