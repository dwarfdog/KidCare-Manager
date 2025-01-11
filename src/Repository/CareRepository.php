<?php

namespace App\Repository;

use App\Entity\Care;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Care>
 */
class CareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Care::class);
    }

    public function findConflictingCare(int $nannyId, string $date, string $startTime, string $endTime): ?Care
    {
        $qb = $this->createQueryBuilder('c');
        $qb->where('c.nanny = :nannyId')
        ->andWhere('c.date = :date')
        ->andWhere('(
                (c.startTime <= :startTime AND c.endTime > :startTime) OR
                (c.startTime < :endTime AND c.endTime >= :endTime) OR
                (c.startTime >= :startTime AND c.endTime <= :endTime)
            )')
        ->setParameter('nannyId', $nannyId)
        ->setParameter('date', $date)
        ->setParameter('startTime', new \DateTime($startTime), \Doctrine\DBAL\Types\Types::TIME_MUTABLE)
        ->setParameter('endTime', new \DateTime($endTime), \Doctrine\DBAL\Types\Types::TIME_MUTABLE);

        return $qb->getQuery()->getOneOrNullResult();
    }


    //    /**
    //     * @return Care[] Returns an array of Care objects
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

    //    public function findOneBySomeField($value): ?Care
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
