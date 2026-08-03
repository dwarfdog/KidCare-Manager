<?php

namespace App\Repository;

use App\Entity\MonthlyPayment;
use App\Entity\Nanny;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MonthlyPayment> */
class MonthlyPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthlyPayment::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?MonthlyPayment
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.user = :user')
            ->andWhere('mp.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneFor(User $user, ?Nanny $nanny, string $month, ?string $nannySnapshotKey = null): ?MonthlyPayment
    {
        $nannySnapshotKey ??= $nanny?->getPublicId();

        $qb = $this->createQueryBuilder('mp')
            ->andWhere('mp.user = :user')
            ->andWhere('mp.month = :month')
            ->setParameter('user', $user)
            ->setParameter('month', $month)
            ->setMaxResults(1);

        if ($nanny !== null && $nannySnapshotKey !== null) {
            $qb
                ->andWhere('(mp.nanny = :nanny OR mp.nannySnapshotKey = :nannySnapshotKey)')
                ->setParameter('nanny', $nanny)
                ->setParameter('nannySnapshotKey', $nannySnapshotKey);
        } elseif ($nannySnapshotKey !== null) {
            $qb
                ->andWhere('mp.nannySnapshotKey = :nannySnapshotKey')
                ->setParameter('nannySnapshotKey', $nannySnapshotKey);
        } else {
            $qb->andWhere('mp.nanny IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** @return MonthlyPayment[] */
    public function findAllExceptCurrentMonth(User $user): array
    {
        $currentMonth = (new \DateTime())->format('Y-m');

        return $this->createQueryBuilder('mp')
            ->andWhere('mp.user = :user')
            ->andWhere('mp.month != :currentMonth')
            ->setParameter('user', $user)
            ->setParameter('currentMonth', $currentMonth)
            ->orderBy('mp.month', 'DESC')
            ->addOrderBy('mp.nannyDisplayName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return MonthlyPayment[] */
    public function findRecentBeforeCurrentMonth(User $user, int $limit = 8): array
    {
        $currentMonth = (new \DateTimeImmutable())->format('Y-m');

        return $this->createQueryBuilder('mp')
            ->andWhere('mp.user = :user')
            ->andWhere('mp.month < :currentMonth')
            ->setParameter('user', $user)
            ->setParameter('currentMonth', $currentMonth)
            ->orderBy('mp.month', 'DESC')
            ->addOrderBy('mp.nannyDisplayName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return MonthlyPayment[] */
    public function findCurrentMonth(User $user): array
    {
        $currentMonth = (new \DateTime())->format('Y-m');

        return $this->createQueryBuilder('mp')
            ->andWhere('mp.user = :user')
            ->andWhere('mp.month = :currentMonth')
            ->setParameter('user', $user)
            ->setParameter('currentMonth', $currentMonth)
            ->orderBy('mp.nannyDisplayName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return MonthlyPayment[] */
    public function findUnpaidBeforeMonth(User $user, string $month): array
    {
        return $this->createQueryBuilder('mp')->andWhere('mp.user = :user')->andWhere('mp.month < :month')->andWhere('mp.isPaid = false')
            ->setParameter('user', $user)->setParameter('month', $month)->orderBy('mp.month', 'ASC')->getQuery()->getResult();
    }
}
