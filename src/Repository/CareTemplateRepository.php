<?php

namespace App\Repository;

use App\Entity\CareTemplate;
use App\Entity\Nanny;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CareTemplate> */
class CareTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CareTemplate::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?CareTemplate
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsForUserNannyAndName(User $user, Nanny $nanny, string $name, ?CareTemplate $excludedTemplate = null): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.user = :user')
            ->andWhere('t.nanny = :nanny')
            ->andWhere('LOWER(t.name) = :name')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->setParameter('name', mb_strtolower(trim($name)));

        if ($excludedTemplate?->getId() !== null) {
            $qb->andWhere('t.id != :excludedId')
                ->setParameter('excludedId', $excludedTemplate->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return CareTemplate[] */
    public function findByUserAndNanny(User $user, Nanny $nanny): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.nanny = :nanny')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return CareTemplate[] */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
