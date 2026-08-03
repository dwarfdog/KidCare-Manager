<?php

namespace App\Repository;

use App\Entity\Nanny;
use App\Entity\NannyDocument;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NannyDocument> */
final class NannyDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, NannyDocument::class); }
    /** @return NannyDocument[] */
    public function findActiveFor(User $user, Nanny $nanny): array { return $this->findBy(['user' => $user, 'nanny' => $nanny, 'archivedAt' => null], ['createdAt' => 'DESC']); }
    public function findOneForUser(User $user, string $publicId, bool $activeOnly = true): ?NannyDocument { $criteria = ['user' => $user, 'publicId' => $publicId]; if ($activeOnly) { $criteria['archivedAt'] = null; } return $this->findOneBy($criteria); }
    /** @return NannyDocument[] */
    public function findArchivedForUser(User $user, int $limit = 200): array { return $this->createQueryBuilder('d')->andWhere('d.user = :user')->andWhere('d.archivedAt IS NOT NULL')->setParameter('user', $user)->orderBy('d.archivedAt', 'DESC')->setMaxResults($limit)->getQuery()->getResult(); }
    /** @return NannyDocument[] */
    public function findExpiring(User $user, \DateTimeInterface $to): array { return $this->createQueryBuilder('d')->andWhere('d.user = :user')->andWhere('d.archivedAt IS NULL')->andWhere('d.expiresAt IS NOT NULL AND d.expiresAt <= :to')->setParameter('user', $user)->setParameter('to', $to)->getQuery()->getResult(); }
}
