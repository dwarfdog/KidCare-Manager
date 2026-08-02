<?php

namespace App\Repository;

use App\Entity\BankAccount;
use App\Entity\BankTransaction;
use App\Entity\MonthlyPayment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankTransaction> */
class BankTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankTransaction::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?BankTransaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankTransaction[] */
    public function findRecentForUser(User $user, int $limit = 20): array
    {
        // Operations recentes deja passees, sur comptes actifs: on exclut le futur (prevu) et les comptes archives.
        return $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->andWhere('t.user = :user')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('a.archivedAt IS NULL')
            ->andWhere('t.date <= :today')
            ->andWhere('t.status IN (:realStatuses)')
            ->setParameter('user', $user)
            ->setParameter('today', (new \DateTimeImmutable('today'))->format('Y-m-d'))
            ->setParameter('realStatuses', $this->realStatuses())
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return BankTransaction[] */
    public function findForAccount(BankAccount $account, int $limit = 200): array
    {
        return $this->findRealForAccount($account, $limit);
    }

    /** @return BankTransaction[] */
    public function findRealForAccount(BankAccount $account, int $limit = 200): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.account = :account')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.status IN (:realStatuses)')
            ->setParameter('account', $account)
            ->setParameter('realStatuses', $this->realStatuses())
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return BankTransaction[] */
    public function findPlannedForAccountUntil(BankAccount $account, \DateTimeInterface $to, int $limit = 80): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.account = :account')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.status = :status')
            ->andWhere('t.date >= :fromDate')
            ->andWhere('t.date <= :toDate')
            ->setParameter('account', $account)
            ->setParameter('status', BankTransaction::STATUS_PLANNED)
            ->setParameter('fromDate', (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'))
            ->setParameter('toDate', $to->format('Y-m-d'))
            ->orderBy('t.date', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return BankTransaction[] */
    public function findArchivedForUser(User $user, int $limit = 200): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.archivedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('t.archivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return BankTransaction[] */
    public function findForAccountPeriod(BankAccount $account, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.account = :account')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.date BETWEEN :fromDate AND :toDate')
            ->setParameter('account', $account)
            ->setParameter('fromDate', $from->format('Y-m-d'))
            ->setParameter('toDate', $to->format('Y-m-d'))
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return BankTransaction[] */
    public function findForPeriod(User $user, \DateTimeInterface $from, \DateTimeInterface $to, bool $realOnly = false): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->andWhere('t.user = :user')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('a.archivedAt IS NULL')
            ->andWhere('t.date BETWEEN :fromDate AND :toDate')
            ->setParameter('user', $user)
            ->setParameter('fromDate', $from->format('Y-m-d'))
            ->setParameter('toDate', $to->format('Y-m-d'))
            ->orderBy('t.date', 'ASC')
            ->addOrderBy('t.id', 'ASC');

        if ($realOnly) {
            $qb->andWhere('t.status IN (:statuses)')
                ->setParameter('statuses', $this->realStatuses());
        }

        return $qb->getQuery()->getResult();
    }

    public function sumRealForAccount(BankAccount $account): float
    {
        $sum = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->andWhere('t.account = :account')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('account', $account)
            ->setParameter('statuses', $this->realStatuses())
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $sum;
    }

    public function sumPlannedForAccountUntil(BankAccount $account, \DateTimeInterface $from, \DateTimeInterface $to): float
    {
        if ($from > $to) {
            return 0.0;
        }

        // Somme de periode de vie: les recurrences "reservees a la periode suivante" sont exclues.
        $sum = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->leftJoin('t.recurringRule', 'r')
            ->andWhere('t.account = :account')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.status = :status')
            ->andWhere('t.date BETWEEN :fromDate AND :toDate')
            ->andWhere('r.id IS NULL OR r.periodAttachment <> :nextAttachment')
            ->setParameter('account', $account)
            ->setParameter('status', BankTransaction::STATUS_PLANNED)
            ->setParameter('fromDate', $from->format('Y-m-d'))
            ->setParameter('toDate', $to->format('Y-m-d'))
            ->setParameter('nextAttachment', \App\Entity\BankRecurringRule::ATTACHMENT_NEXT)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $sum;
    }

    public function sumRealForUser(User $user): float
    {
        $sum = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->andWhere('t.user = :user')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', $this->realStatuses())
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $sum;
    }

    /** @return BankTransaction[] */
    public function findDuePlanned(User $user, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.status = :status')
            ->andWhere('t.date <= :date')
            ->setParameter('user', $user)
            ->setParameter('status', BankTransaction::STATUS_PLANNED)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function findOneByImportHash(User $user, BankAccount $account, string $hash): ?BankTransaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.account = :account')
            ->andWhere('t.importHash = :hash')
            ->setParameter('user', $user)
            ->setParameter('account', $account)
            ->setParameter('hash', $hash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForMonthlyPayment(User $user, MonthlyPayment $payment): ?BankTransaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.monthlyPayment = :payment')
            ->andWhere('t.source = :source')
            ->andWhere('t.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('payment', $payment)
            ->setParameter('source', BankTransaction::SOURCE_KIDCARE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankTransaction[] */
    public function findAllForLoan(BankAccount $loanAccount): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.loanAccount = :loan')
            ->andWhere('t.archivedAt IS NULL')
            ->setParameter('loan', $loanAccount)
            ->getQuery()
            ->getResult();
    }

    /** @return BankTransaction[] */
    public function findPlannedForLoan(BankAccount $loanAccount, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.loanAccount = :loan')
            ->andWhere('t.status = :status')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.date <= :to')
            ->setParameter('loan', $loanAccount)
            ->setParameter('status', BankTransaction::STATUS_PLANNED)
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    /** @return BankTransaction[] */
    public function findFuturePlannedForLoan(BankAccount $loanAccount, \DateTimeInterface $afterDate): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.loanAccount = :loan')
            ->andWhere('t.status = :status')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.date > :afterDate')
            ->setParameter('loan', $loanAccount)
            ->setParameter('status', BankTransaction::STATUS_PLANNED)
            ->setParameter('afterDate', $afterDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function findOneByLoanAndDate(BankAccount $loanAccount, \DateTimeInterface $date): ?BankTransaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.loanAccount = :loan')
            ->andWhere('t.date = :date')
            ->andWhere('t.archivedAt IS NULL')
            ->setParameter('loan', $loanAccount)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByRuleAndDate(User $user, \App\Entity\BankRecurringRule $rule, \DateTimeInterface $date): ?BankTransaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.recurringRule = :rule')
            ->andWhere('t.date = :date')
            ->andWhere('t.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('rule', $rule)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankTransaction[] */
    public function findPlannedForRule(\App\Entity\BankRecurringRule $rule): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.recurringRule = :rule')
            ->andWhere('t.status = :status')
            ->andWhere('t.archivedAt IS NULL')
            ->setParameter('rule', $rule)
            ->setParameter('status', BankTransaction::STATUS_PLANNED)
            ->getQuery()
            ->getResult();
    }

    /**
     * Operations planifiees rattachees a une recurrence desormais inactive (a ne plus compter).
     *
     * @return BankTransaction[]
     */
    public function findPlannedForInactiveRules(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.recurringRule', 'r')
            ->andWhere('t.user = :user')
            ->andWhere('t.status = :status')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('r.isActive = false')
            ->setParameter('user', $user)
            ->setParameter('status', BankTransaction::STATUS_PLANNED)
            ->getQuery()
            ->getResult();
    }

    /** @return BankTransaction[] */
    public function findFuturePlannedForRule(\App\Entity\BankRecurringRule $rule, \DateTimeInterface $afterDate): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.recurringRule = :rule')
            ->andWhere('t.status = :status')
            ->andWhere('t.date > :afterDate')
            ->andWhere('t.archivedAt IS NULL')
            ->setParameter('rule', $rule)
            ->setParameter('status', BankTransaction::STATUS_PLANNED)
            ->setParameter('afterDate', $afterDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    private function realStatuses(): array
    {
        return [BankTransaction::STATUS_REAL, BankTransaction::STATUS_RECONCILED];
    }
}
