<?php

namespace App\Repository;

use App\Entity\BankCategoryRule;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankCategoryRule> */ final class BankCategoryRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, BankCategoryRule::class);
    }
    /** @return BankCategoryRule[] */ public function findActiveForUser(User $u): array
    {
        return $this->createQueryBuilder('r')->andWhere('r.user=:u')->andWhere('r.active=true')->andWhere('r.archivedAt IS NULL')->setParameter('u', $u)->orderBy('r.priority', 'DESC')->addOrderBy('r.createdAt', 'ASC')->getQuery()->getResult();
    }
    /** @return BankCategoryRule[] */ public function findAllForUser(User $u): array
    {
        return $this->findBy(['user' => $u, 'archivedAt' => null], ['priority' => 'DESC', 'createdAt' => 'ASC']);
    }
    public function findOneForUser(User $u, string $id): ?BankCategoryRule
    {
        return $this->findOneBy(['user' => $u, 'publicId' => $id, 'archivedAt' => null]);
    }
    /** @return BankCategoryRule[] */ public function findArchivedForUser(User $u, int $limit = 200): array
    {
        return $this->createQueryBuilder('r')->andWhere('r.user=:u')->andWhere('r.archivedAt IS NOT NULL')->setParameter('u', $u)->orderBy('r.archivedAt', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }
}
