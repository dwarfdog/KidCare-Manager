<?php

namespace App\Repository;

use App\Entity\BankTransaction;
use App\Entity\BankTransactionSplit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankTransactionSplit> */
class BankTransactionSplitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, BankTransactionSplit::class); }
    /** @return BankTransactionSplit[] */
    public function findForTransaction(BankTransaction $transaction): array { return $this->findBy(['transaction' => $transaction], ['id' => 'ASC']); }
    public function findOneForUser(User $user, string $publicId): ?BankTransactionSplit { return $this->findOneBy(['user' => $user, 'publicId' => $publicId]); }
    public function hasSplits(BankTransaction $transaction): bool { return $this->count(['transaction' => $transaction]) > 0; }
}
