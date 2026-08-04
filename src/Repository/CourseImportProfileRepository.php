<?php

namespace App\Repository;

use App\Entity\CourseImportProfile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseImportProfile> */
class CourseImportProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseImportProfile::class);
    }

    /** @return CourseImportProfile[] */
    public function findAllForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['name' => 'ASC']);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?CourseImportProfile
    {
        return $this->findOneBy(['user' => $user, 'publicId' => $publicId]);
    }
}
