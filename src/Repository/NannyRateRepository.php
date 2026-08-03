<?php

namespace App\Repository;

use App\Entity\Nanny;
use App\Entity\NannyRate;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NannyRate> */
class NannyRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NannyRate::class);
    }

    public function findApplicableRate(User $user, Nanny $nanny, \DateTimeInterface $date): ?NannyRate
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('(r.nanny = :nanny OR r.nannySnapshotKey = :nannySnapshotKey)')
            ->andWhere('r.effectiveFrom <= :date')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->setParameter('nannySnapshotKey', $nanny->getPublicId())
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('r.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?NannyRate
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.publicId = :publicId')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findApplicableRateForSnapshot(User $user, string $nannySnapshotKey, \DateTimeInterface $date): ?NannyRate
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.nannySnapshotKey = :nannySnapshotKey')
            ->andWhere('r.effectiveFrom <= :date')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('nannySnapshotKey', $nannySnapshotKey)
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('r.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return NannyRate[] */
    public function findForUserAndNanny(User $user, Nanny $nanny): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('(r.nanny = :nanny OR r.nannySnapshotKey = :nannySnapshotKey)')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->setParameter('nannySnapshotKey', $nanny->getPublicId())
            ->orderBy('r.effectiveFrom', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function existsForEffectiveDate(User $user, Nanny $nanny, \DateTimeInterface $effectiveFrom, ?NannyRate $excludedRate = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('(r.nanny = :nanny OR r.nannySnapshotKey = :nannySnapshotKey)')
            ->andWhere('r.effectiveFrom = :effectiveFrom')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->setParameter('nannySnapshotKey', $nanny->getPublicId())
            ->setParameter('effectiveFrom', $effectiveFrom->format('Y-m-d'));

        if ($excludedRate?->getId() !== null) {
            $qb->andWhere('r.id != :excludedId')->setParameter('excludedId', $excludedRate->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findNextRate(User $user, Nanny $nanny, \DateTimeInterface $from, ?NannyRate $excludedRate = null): ?NannyRate
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('(r.nanny = :nanny OR r.nannySnapshotKey = :nannySnapshotKey)')
            ->andWhere('r.effectiveFrom > :fromDate')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->setParameter('nannySnapshotKey', $nanny->getPublicId())
            ->setParameter('fromDate', $from->format('Y-m-d'))
            ->orderBy('r.effectiveFrom', 'ASC')
            ->setMaxResults(1);

        if ($excludedRate?->getId() !== null) {
            $qb->andWhere('r.id != :excludedId')->setParameter('excludedId', $excludedRate->getId());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** @return NannyRate[] */
    public function findArchivedForUser(User $user): array
    {
        return $this->createQueryBuilder('r')->andWhere('r.user = :user')->andWhere('r.archivedAt IS NOT NULL')->setParameter('user', $user)->orderBy('r.archivedAt', 'DESC')->getQuery()->getResult();
    }
}
