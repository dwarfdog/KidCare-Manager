<?php

namespace App\Repository;

use App\Entity\BankTransaction;
use App\Entity\CourseReceipt;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseReceipt> */
class CourseReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseReceipt::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?CourseReceipt
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return CourseReceipt[] */
    public function findPendingReviewForUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('statuses', [CourseReceipt::STATUS_PROCESSING, CourseReceipt::STATUS_PENDING_REVIEW, CourseReceipt::STATUS_FAILED])
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return CourseReceipt[] */
    public function findConfirmedForUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.status = :status')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('status', CourseReceipt::STATUS_CONFIRMED)
            ->orderBy('r.purchaseDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return CourseReceipt[] */
    public function findConfirmedForUserPeriod(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.status = :status')
            ->andWhere('r.archivedAt IS NULL')
            ->andWhere('r.purchaseDate BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('status', CourseReceipt::STATUS_CONFIRMED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('r.purchaseDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForBankTransaction(User $user, BankTransaction $transaction): ?CourseReceipt
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.bankTransaction = :transaction')
            ->setParameter('user', $user)
            ->setParameter('transaction', $transaction)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return CourseReceipt[] */
    public function findArchivedForUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.archivedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('r.archivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
