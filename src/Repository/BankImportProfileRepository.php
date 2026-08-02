<?php

namespace App\Repository;

use App\Entity\BankAccount;
use App\Entity\BankImportProfile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankImportProfile> */
class BankImportProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankImportProfile::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?BankImportProfile
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BankImportProfile[] */
    public function findForAccount(User $user, BankAccount $account): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.account = :account OR p.account IS NULL')
            ->setParameter('user', $user)
            ->setParameter('account', $account)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
