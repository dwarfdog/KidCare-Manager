<?php

namespace App\Repository;

use App\Entity\BankSimulation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankSimulation> */ final class BankSimulationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, BankSimulation::class);
    }
    public function findActiveForUser(User $u): array
    {
        return $this->findBy(['user' => $u, 'archivedAt' => null], ['createdAt' => 'DESC']);
    }
    public function findOneForUser(User $u, string $id): ?BankSimulation
    {
        return $this->findOneBy(['user' => $u, 'publicId' => $id, 'archivedAt' => null]);
    }
    public function findArchivedForUser(User $u): array
    {
        return $this->createQueryBuilder('s')->andWhere('s.user=:u')->andWhere('s.archivedAt IS NOT NULL')->setParameter('u', $u)->getQuery()->getResult();
    }
}
