<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\MonthlyPayment;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<MonthlyPayment>
 */
class MonthlyPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthlyPayment::class);
    }

    public function findAllExceptCurrentMonth(User $user)
    {
        $currentDate = new \DateTime();
        $currentMonth = $currentDate->format('Y-m');

        return $this->createQueryBuilder('mp')
            ->where('mp.user = :user')
            ->andWhere('mp.month != :currentMonth')
            ->setParameter('user', $user)
            ->setParameter('currentMonth', $currentMonth)
            ->orderBy('mp.month', 'DESC')
            ->getQuery()
            ->getResult();
    }
    //    /**
    //     * @return MonthlyPayment[] Returns an array of MonthlyPayment objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?MonthlyPayment
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
