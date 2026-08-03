<?php

namespace App\Repository;

use App\Entity\CalendarSubscription;
use App\Entity\Nanny;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CalendarSubscription> */
final class CalendarSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, CalendarSubscription::class); }
    public function findFor(User $user, Nanny $nanny): ?CalendarSubscription { return $this->findOneBy(['user' => $user, 'nanny' => $nanny]); }
    public function findOneByToken(string $token): ?CalendarSubscription { return $this->findOneBy(['accessToken' => $token]); }
}
