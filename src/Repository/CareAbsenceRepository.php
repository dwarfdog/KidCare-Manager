<?php

namespace App\Repository;

use App\Entity\CareAbsence;
use App\Entity\Nanny;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CareAbsence> */
final class CareAbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, CareAbsence::class); }

    /** @return CareAbsence[] */
    public function findActiveFor(User $user, Nanny $nanny): array
    {
        return $this->createQueryBuilder('a')->andWhere('a.user = :user')->andWhere('a.nanny = :nanny')->andWhere('a.archivedAt IS NULL')
            ->setParameter('user', $user)->setParameter('nanny', $nanny)->orderBy('a.startsAt', 'ASC')->getQuery()->getResult();
    }

    /** @return CareAbsence[] */
    public function findBetween(User $user, Nanny $nanny, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('a')->andWhere('a.user = :user')->andWhere('a.nanny = :nanny')->andWhere('a.archivedAt IS NULL')
            ->andWhere('a.startsAt < :to')->andWhere('a.endsAt > :from')->setParameter('user', $user)->setParameter('nanny', $nanny)
            ->setParameter('from', $from)->setParameter('to', $to)->orderBy('a.startsAt', 'ASC')->getQuery()->getResult();
    }

    public function findOneForUser(User $user, string $publicId): ?CareAbsence
    {
        return $this->findOneBy(['user' => $user, 'publicId' => $publicId, 'archivedAt' => null]);
    }

    /** @return CareAbsence[] */
    public function findArchivedForUser(User $user, int $limit = 200): array
    {
        return $this->createQueryBuilder('a')->andWhere('a.user = :user')->andWhere('a.archivedAt IS NOT NULL')->setParameter('user', $user)
            ->orderBy('a.archivedAt', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }
}
