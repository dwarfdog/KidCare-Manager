<?php

namespace App\Repository;

use App\Entity\BankAlertSetting;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankAlertSetting> */ final class BankAlertSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, BankAlertSetting::class);
    }
    public function findActiveForUser(User $u): array
    {
        return $this->findBy(['user' => $u, 'active' => true], ['targetType' => 'ASC', 'type' => 'ASC']);
    }
    public function findAllForUser(User $u): array
    {
        return $this->findBy(['user' => $u], ['targetType' => 'ASC', 'type' => 'ASC']);
    }
    public function findOneForUser(User $u, string $id): ?BankAlertSetting
    {
        return $this->findOneBy(['user' => $u, 'publicId' => $id]);
    }
}
