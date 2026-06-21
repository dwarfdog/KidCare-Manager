<?php

namespace App\Repository;

use App\Entity\Nanny;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Nanny> */
class NannyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Nanny::class);
    }

    public function findOneByPublicId(string $publicId): ?Nanny
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function findOneByInviteCode(string $inviteCode): ?Nanny
    {
        return $this->findOneBy(['inviteCode' => trim($inviteCode)]);
    }

    /** @return Nanny[] */
    public function findAssignedToUser(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->innerJoin('n.assignments', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('a.detachedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('n.lastname', 'ASC')
            ->addOrderBy('n.firstname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Nanny[] */
    public function searchAttachable(User $user, ?string $query): array
    {
        $query = trim((string) $query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $qb = $this->createQueryBuilder('n')
            ->leftJoin('n.assignments', 'own', 'WITH', 'own.user = :user AND own.detachedAt IS NULL')
            ->andWhere('own.id IS NULL')
            ->setParameter('user', $user)
            ->orderBy('n.lastname', 'ASC')
            ->addOrderBy('n.firstname', 'ASC')
            ->setMaxResults(30);

        $qb->andWhere('LOWER(n.firstname) LIKE :query OR LOWER(n.lastname) LIKE :query OR LOWER(n.city) LIKE :query OR LOWER(n.postalCode) LIKE :query')
            ->setParameter('query', '%' . mb_strtolower($query) . '%');

        return $qb->getQuery()->getResult();
    }

    public function findDuplicateIdentity(Nanny $nanny): ?Nanny
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('LOWER(n.firstname) = :firstname')
            ->andWhere('LOWER(n.lastname) = :lastname')
            ->andWhere('LOWER(COALESCE(n.postalCode, \'\')) = :postalCode')
            ->andWhere('LOWER(COALESCE(n.city, \'\')) = :city')
            ->setParameter('firstname', mb_strtolower((string) $nanny->getFirstname()))
            ->setParameter('lastname', mb_strtolower((string) $nanny->getLastname()))
            ->setParameter('postalCode', mb_strtolower((string) $nanny->getPostalCode()))
            ->setParameter('city', mb_strtolower((string) $nanny->getCity()))
            ->setMaxResults(1);

        if ($nanny->getId() !== null) {
            $qb->andWhere('n.id != :id')->setParameter('id', $nanny->getId());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
