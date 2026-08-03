<?php

namespace App\Repository;

use App\Entity\BankReconciliationBatch;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankReconciliationBatch> */ final class BankReconciliationBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, BankReconciliationBatch::class);
    }
    public function findRecentForUser(User $u): array
    {
        return $this->findBy(['user' => $u], ['createdAt' => 'DESC'], 20);
    }
}
