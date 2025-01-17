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

    /**
     * Trouve la prochaine garde qui n'a pas encore commencé.
     *
     * Cette fonction retourne la prochaine garde basée sur la date actuelle et l'heure courante.
     * Elle traite deux cas principaux :
     * 1. Si une garde est prévue aujourd'hui et commence après l'heure actuelle, elle est retournée.
     * 2. Sinon, la première garde des jours suivants est retournée.
     *
     * @return Care|null La prochaine garde, ou null s'il n'y a pas de garde prévue.
     */
    public function findNextCare(): ?Care
    {
        // Obtenir la date et l'heure actuelles
        $currentDateTime = new \DateTime();
        $currentTime = $currentDateTime->format('H:i:s');
        $currentDate = $currentDateTime->format('Y-m-d');

        // Recherche des gardes du jour même après l'heure actuelle
        $nextTodayCare = $this->createQueryBuilder('c')
            ->where('c.date = :currentDate')
            ->andWhere('c.startTime > :currentTime')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('currentTime', $currentTime)
            ->orderBy('c.date', 'ASC') // Tri par date croissante
            ->addOrderBy('c.startTime', 'ASC') // Puis par heure de début
            ->setMaxResults(1) // Limite à une seule garde
            ->getQuery()
            ->getOneOrNullResult();

        // Si une garde du jour est trouvée, la retourner
        if ($nextTodayCare) {
            return $nextTodayCare;
        }

        // Recherche des gardes des jours suivants
        return $this->createQueryBuilder('c')
            ->where('(c.date = :currentDate AND c.startTime > :currentTime)') // Gardes restantes du jour actuel
            ->orWhere('c.date > :currentDate') // Gardes des jours suivants
            ->setParameter('currentDate', $currentDate)
            ->setParameter('currentTime', $currentTime)
            ->orderBy('c.date', 'ASC') // Tri par date croissante
            ->addOrderBy('c.startTime', 'ASC') // Puis par heure de début
            ->setMaxResults(1) // Limite à une seule garde
            ->getQuery()
            ->getOneOrNullResult();
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
