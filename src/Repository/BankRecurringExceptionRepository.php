<?php

namespace App\Repository;

use App\Entity\BankRecurringException;
use App\Entity\BankRecurringRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankRecurringException> */
class BankRecurringExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankRecurringException::class);
    }

    /** @return array<string, BankRecurringException> */
    public function findIndexedForRule(BankRecurringRule $rule): array
    {
        $exceptions = $this->createQueryBuilder('e')
            ->andWhere('e.rule = :rule')
            ->setParameter('rule', $rule)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($exceptions as $exception) {
            $indexed[$exception->getExceptionDate()->format('Y-m-d')] = $exception;
        }

        return $indexed;
    }
}
