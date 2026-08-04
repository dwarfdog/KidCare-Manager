<?php

namespace App\Repository;

use App\Entity\CourseStoreAlias;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseStoreAlias> */
class CourseStoreAliasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseStoreAlias::class);
    }

    public function findOneForUserByLabel(User $user, string $normalizedLabel): ?CourseStoreAlias
    {
        return $this->findOneBy(['user' => $user, 'normalizedLabel' => $normalizedLabel]);
    }
}
