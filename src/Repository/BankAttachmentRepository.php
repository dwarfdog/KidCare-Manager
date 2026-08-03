<?php

namespace App\Repository;

use App\Entity\BankAttachment;
use App\Entity\BankTransaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankAttachment> */
class BankAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankAttachment::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?BankAttachment
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankAttachment[] */
    public function findActiveForTransaction(BankTransaction $transaction): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.transaction = :transaction')
            ->andWhere('a.archivedAt IS NULL')
            ->setParameter('transaction', $transaction)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return BankAttachment[] */
    public function findArchivedForUser(User $user): array
    {
        return $this->createQueryBuilder('a')->andWhere('a.user=:user')->andWhere('a.archivedAt IS NOT NULL')->setParameter('user', $user)->orderBy('a.archivedAt', 'DESC')->getQuery()->getResult();
    }
}
