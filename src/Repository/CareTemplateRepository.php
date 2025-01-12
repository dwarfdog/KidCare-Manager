<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Nanny;
use App\Entity\CareTemplate;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<CareTemplate>
 */
class CareTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CareTemplate::class);
    }

    public function findByUserAndNanny(User $user, Nanny $nanny): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.nanny = :nanny')
            ->setParameter('user', $user)
            ->setParameter('nanny', $nanny)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return CareTemplate[] Returns an array of CareTemplate objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?CareTemplate
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
