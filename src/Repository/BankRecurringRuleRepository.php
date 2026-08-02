<?php

namespace App\Repository;

use App\Entity\BankRecurringRule;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankRecurringRule> */
class BankRecurringRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankRecurringRule::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?BankRecurringRule
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankRecurringRule[] */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{account?: ?\App\Entity\BankAccount, category?: ?\App\Entity\BankCategory, direction?: ?string, active?: ?bool} $filters
     *
     * @return BankRecurringRule[]
     */
    public function findFilteredForUser(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.isActive', 'DESC')
            ->addOrderBy('r.startDate', 'ASC');

        if (($filters['active'] ?? null) !== null) {
            $qb->andWhere('r.isActive = :active')->setParameter('active', $filters['active']);
        }
        if (($filters['account'] ?? null) !== null) {
            $qb->andWhere('r.account = :account')->setParameter('account', $filters['account']);
        }
        if (($filters['category'] ?? null) !== null) {
            $qb->andWhere('r.category = :category')->setParameter('category', $filters['category']);
        }
        if (($filters['direction'] ?? null) === 'income') {
            $qb->andWhere('r.amount >= 0');
        } elseif (($filters['direction'] ?? null) === 'expense') {
            $qb->andWhere('r.amount < 0');
        }

        return $qb->getQuery()->getResult();
    }
}
