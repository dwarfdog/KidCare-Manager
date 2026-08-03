<?php

namespace App\Repository;

use App\Entity\BankImportBatch;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankImportBatch> */
class BankImportBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankImportBatch::class);
    }

    /** @return BankImportBatch[] */
    public function findRecentForUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneForUser(User $user, string $publicId): ?BankImportBatch
    {
        return $this->findOneBy(['user' => $user, 'publicId' => $publicId]);
    }
}
