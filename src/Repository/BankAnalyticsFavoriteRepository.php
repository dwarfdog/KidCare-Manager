<?php

namespace App\Repository;

use App\Entity\BankAnalyticsFavorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankAnalyticsFavorite> */ final class BankAnalyticsFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, BankAnalyticsFavorite::class);
    }
    public function findForUser(User $u): array
    {
        return $this->findBy(['user' => $u], ['name' => 'ASC']);
    }
    public function findOneForUser(User $u, string $id): ?BankAnalyticsFavorite
    {
        return $this->findOneBy(['user' => $u, 'publicId' => $id]);
    }
}
