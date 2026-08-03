<?php

namespace App\Repository;

use App\Entity\BankAccount;
use App\Entity\BankTransaction;
use App\Entity\MonthlyPayment;
use App\Entity\BankImportBatch;
use App\Entity\User;
use App\Model\BankTransactionListCriteria;
use App\Model\BankTransactionPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    public function findRealPageForAccount(BankAccount $account, BankTransactionListCriteria $criteria): BankTransactionPage
    {
        $query = $this->accountListQuery($account, $criteria, false)->getQuery();
        $paginator = new Paginator($query, true);
        $total = count($paginator);
        $pages = max(1, (int) ceil($total / $criteria->perPage));
        $page = min($criteria->page, $pages);

        $query
            ->setFirstResult(($page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage);

        /** @var BankTransaction[] $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return new BankTransactionPage($items, $total, $page, $pages, $criteria->perPage);
    }

    public function findRealPageNumberForTransaction(BankAccount $account, BankTransaction $transaction, BankTransactionListCriteria $criteria): ?int
    {
        $rows = $this->accountListQuery($account, $criteria->withPage(1), false)
            ->select('t.publicId AS publicId')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $position => $row) {
            if (($row['publicId'] ?? null) === $transaction->getPublicId()) {
                return intdiv($position, $criteria->perPage) + 1;
            }
        }

        return null;
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
    public function findPlannedForAccountUntilFiltered(BankAccount $account, \DateTimeInterface $to, BankTransactionListCriteria $criteria): array
    {
        $from = new \DateTimeImmutable('tomorrow');
        if ($criteria->from !== null && $criteria->from > $from) {
            $from = $criteria->from;
        }

        $periodEnd = \DateTimeImmutable::createFromInterface($to);
        if ($criteria->to !== null && $criteria->to < $periodEnd) {
            $periodEnd = $criteria->to;
        }
        if ($from > $periodEnd) {
            return [];
        }

        return $this->accountListQuery($account, $criteria, true)
            ->andWhere('t.date >= :plannedFrom')
            ->andWhere('t.date <= :plannedTo')
            ->setParameter('plannedFrom', $from->format('Y-m-d'))
            ->setParameter('plannedTo', $periodEnd->format('Y-m-d'))
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

    /**
     * @param BankAccount[] $accounts
     * @return array<int, array{month: string, gains: float, expenses: float}>
     */
    public function monthlyEvolutionForCategory(User $user, \App\Entity\BankCategory $category, array $accounts, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $accountIds = array_values(array_filter(array_map(static fn (BankAccount $account): ?int => $account->getId(), $accounts)));
        if ($accountIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            <<<'SQL'
                SELECT substr(transaction_date, 1, 7) AS month,
                       SUM(CASE WHEN amount > 0 THEN CAST(amount AS REAL) ELSE 0 END) AS gains,
                       SUM(CASE WHEN amount < 0 THEN ABS(CAST(amount AS REAL)) ELSE 0 END) AS expenses
                FROM bank_transaction
                WHERE user_id = :userId
                  AND category_id = :categoryId
                  AND account_id IN (:accountIds)
                  AND archived_at IS NULL
                  AND status IN (:statuses)
                  AND transaction_date BETWEEN :fromDate AND :toDate
                GROUP BY substr(transaction_date, 1, 7)
                ORDER BY month ASC
                SQL,
            [
                'userId' => $user->getId(),
                'categoryId' => $category->getId(),
                'accountIds' => $accountIds,
                'statuses' => $this->realStatuses(),
                'fromDate' => $from->format('Y-m-d'),
                'toDate' => $to->format('Y-m-d'),
            ],
            [
                'accountIds' => ArrayParameterType::INTEGER,
                'statuses' => ArrayParameterType::STRING,
            ],
        )->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'month' => (string) $row['month'],
            'gains' => round((float) $row['gains'], 2),
            'expenses' => round((float) $row['expenses'], 2),
        ], $rows);
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

    /** @return BankTransaction[] */
    public function findForImportBatch(User $user, BankImportBatch $batch): array
    {
        return $this->createQueryBuilder('t')->andWhere('t.user = :user')->andWhere('t.importBatch = :batch')->setParameter('user', $user)->setParameter('batch', $batch)->getQuery()->getResult();
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

    /** @return list<string> */
    private function realStatuses(): array
    {
        return [BankTransaction::STATUS_REAL, BankTransaction::STATUS_RECONCILED];
    }

    private function accountListQuery(BankAccount $account, BankTransactionListCriteria $criteria, bool $planned): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'category')
            ->andWhere('t.account = :listAccount')
            ->andWhere('t.archivedAt IS NULL')
            ->setParameter('listAccount', $account);

        if ($planned) {
            $queryBuilder
                ->andWhere('t.status = :listStatus')
                ->setParameter('listStatus', BankTransaction::STATUS_PLANNED);
        } else {
            $queryBuilder
                ->andWhere('t.status IN (:listStatuses)')
                ->setParameter('listStatuses', $this->realStatuses());
        }

        if ($criteria->from !== null && !$planned) {
            $queryBuilder
                ->andWhere('t.date >= :listFrom')
                ->setParameter('listFrom', $criteria->from->format('Y-m-d'));
        }
        if ($criteria->to !== null && !$planned) {
            $queryBuilder
                ->andWhere('t.date <= :listTo')
                ->setParameter('listTo', $criteria->to->format('Y-m-d'));
        }
        if ($criteria->category !== null) {
            $queryBuilder
                ->andWhere('t.category = :listCategory')
                ->setParameter('listCategory', $criteria->category);
        }
        if ($criteria->type !== null) {
            $queryBuilder
                ->andWhere('t.type = :listType')
                ->setParameter('listType', $criteria->type);
        }
        if (!$planned && $criteria->verified !== null) {
            $queryBuilder->andWhere($criteria->verified ? 't.verifiedAt IS NOT NULL' : 't.verifiedAt IS NULL');
        }

        $direction = strtoupper($criteria->direction) === 'ASC' ? 'ASC' : 'DESC';
        $sortField = match ($criteria->sort) {
            'amount' => 't.amount',
            'category' => 'category.name',
            default => 't.date',
        };
        $queryBuilder->orderBy($sortField, $direction);
        if ($sortField !== 't.date') {
            $queryBuilder->addOrderBy('t.date', 'DESC');
        }
        $queryBuilder->addOrderBy('t.id', $direction);

        return $queryBuilder;
    }
}
