<?php

namespace App\Repository;

use App\Entity\Care;
use App\Entity\Nanny;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Care> */
class CareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Care::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?Care
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Care[] */
    public function findForUserAndNanny(User $user, Nanny $nanny): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.nanny = :nanny')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->orderBy('c.date', 'ASC')
            ->addOrderBy('c.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Care[] */
    public function findForPayment(User $user, ?Nanny $nanny, string $month, ?string $nannySnapshotKey = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.month = :month')
            ->setParameter('user', $user)
            ->setParameter('month', $month)
            ->orderBy('c.date', 'ASC')
            ->addOrderBy('c.startTime', 'ASC');

        if ($nanny !== null) {
            $qb->andWhere('c.nanny = :nanny')->setParameter('nanny', $nanny);
        } elseif ($nannySnapshotKey !== null) {
            $qb->andWhere('c.nannySnapshotKey = :nannySnapshotKey')
                ->setParameter('nannySnapshotKey', $nannySnapshotKey);
        } else {
            $qb->andWhere('c.nanny IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    public function findNextCare(User $user): ?Care
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('(c.date > :date OR (c.date = :date AND c.startTime >= :time))')
            ->setParameter('user', $user)
            ->setParameter('date', $now->format('Y-m-d'))
            ->setParameter('time', $now->format('H:i:s'))
            ->orderBy('c.date', 'ASC')
            ->addOrderBy('c.startTime', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasFutureOrUnpaidCare(User $user, Nanny $nanny): bool
    {
        $today = new \DateTimeImmutable('today');

        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->leftJoin('App\Entity\MonthlyPayment', 'mp', 'WITH', 'mp.user = c.user AND mp.nanny = c.nanny AND mp.month = c.month')
            ->andWhere('c.user = :user')
            ->andWhere('c.nanny = :nanny')
            ->andWhere('(c.date >= :today OR mp.isPaid = false OR mp.id IS NULL)')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function hasCareInPaidMonth(User $user, Nanny $nanny, \DateTimeInterface $effectiveFrom): bool
    {
        return $this->hasPaidCareInRange($user, $nanny, $effectiveFrom);
    }

    public function hasPaidCareInRange(User $user, Nanny $nanny, \DateTimeInterface $from, ?\DateTimeInterface $to = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('App\Entity\MonthlyPayment', 'mp', 'WITH', 'mp.user = c.user AND mp.nanny = c.nanny AND mp.month = c.month')
            ->andWhere('c.user = :user')
            ->andWhere('c.nanny = :nanny')
            ->andWhere('c.date >= :fromDate')
            ->andWhere('mp.isPaid = true')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->setParameter('fromDate', $from->format('Y-m-d'));

        if ($to !== null) {
            $qb->andWhere('c.date < :toDate')->setParameter('toDate', $to->format('Y-m-d'));
        }

        $count = $qb->getQuery()->getSingleScalarResult();

        return (int) $count > 0;
    }

    /** @return string[] */
    public function findUnpaidMonthsInRange(User $user, Nanny $nanny, \DateTimeInterface $from, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('DISTINCT c.month')
            ->leftJoin('App\Entity\MonthlyPayment', 'mp', 'WITH', 'mp.user = c.user AND mp.nanny = c.nanny AND mp.month = c.month')
            ->andWhere('c.user = :user')
            ->andWhere('c.nanny = :nanny')
            ->andWhere('c.date >= :fromDate')
            ->andWhere('(mp.id IS NULL OR mp.isPaid = false)')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->setParameter('fromDate', $from->format('Y-m-d'))
            ->orderBy('c.month', 'ASC');

        if ($to !== null) {
            $qb->andWhere('c.date < :toDate')->setParameter('toDate', $to->format('Y-m-d'));
        }

        return array_map(static fn (array $row): string => $row['month'], $qb->getQuery()->getArrayResult());
    }

    public function overlaps(User $user, Nanny $nanny, \DateTimeImmutable $start, \DateTimeImmutable $end, ?Care $ignoredCare = null): bool
    {
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        $cares = $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.nanny = :nanny')
            ->andWhere('c.date BETWEEN :fromDate AND :toDate')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->setParameter('fromDate', $start->modify('-1 day')->format('Y-m-d'))
            ->setParameter('toDate', $end->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        foreach ($cares as $care) {
            if ($ignoredCare !== null && $care->getId() === $ignoredCare->getId()) {
                continue;
            }

            if ($care->getStartDateTime() < $end && $care->getEndDateTime() > $start) {
                return true;
            }
        }

        return false;
    }
}
