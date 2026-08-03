<?php

namespace App\Repository;

use App\Entity\BankCategory;
use App\Entity\BankTransaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankCategory> */
class BankCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankCategory::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?BankCategory
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankCategory[] */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('c.type', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return BankCategory[] */
    public function findActiveWithRealDataForUser(User $user): array
    {
        return $this->createQueryBuilder('category')
            ->innerJoin(BankTransaction::class, 'transaction', Join::ON, 'transaction.category = category')
            ->innerJoin('transaction.account', 'account')
            ->andWhere('category.user = :user')
            ->andWhere('category.archivedAt IS NULL')
            ->andWhere('transaction.archivedAt IS NULL')
            ->andWhere('account.archivedAt IS NULL')
            ->andWhere('transaction.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [BankTransaction::STATUS_REAL, BankTransaction::STATUS_RECONCILED])
            ->groupBy('category.id')
            ->orderBy('category.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByName(User $user, string $name): ?BankCategory
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('LOWER(c.name) = LOWER(:name)')
            ->andWhere('c.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankCategory[] */
    public function findArchivedForUser(User $user): array { return $this->createQueryBuilder('c')->andWhere('c.user=:user')->andWhere('c.archivedAt IS NOT NULL')->setParameter('user',$user)->orderBy('c.archivedAt','DESC')->getQuery()->getResult(); }
}
