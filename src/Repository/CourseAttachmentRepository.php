<?php

namespace App\Repository;

use App\Entity\CourseAttachment;
use App\Entity\CourseReceipt;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseAttachment> */
class CourseAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseAttachment::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?CourseAttachment
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return CourseAttachment[] */
    public function findActiveForReceipt(CourseReceipt $receipt): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.receipt = :receipt')
            ->andWhere('a.archivedAt IS NULL')
            ->setParameter('receipt', $receipt)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
