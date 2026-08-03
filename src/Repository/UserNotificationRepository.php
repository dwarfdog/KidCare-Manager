<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserNotification> */
final class UserNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserNotification::class);
    }
    /** @return UserNotification[] */
    public function findForUser(User $user, int $limit = 100): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC'], $limit);
    }
    /** @return UserNotification[] */
    public function findUnreadForUser(User $user, int $limit = 25): array
    {
        return $this->findBy(['user' => $user, 'readAt' => null], ['createdAt' => 'DESC'], $limit);
    }
    public function countUnread(User $user): int
    {
        return (int) $this->count(['user' => $user, 'readAt' => null]);
    }

    /** @return array{count:int, severity:?string} */
    public function unreadSummary(User $user): array
    {
        $count = $this->countUnread($user);
        if ($count === 0) {
            return ['count' => 0, 'severity' => null];
        }

        $warnings = (int) $this->count(['user' => $user, 'readAt' => null, 'kind' => 'warning']);

        return ['count' => $count, 'severity' => $warnings > 0 ? 'warning' : 'information'];
    }
    public function findOneForUser(User $user, string $publicId): ?UserNotification
    {
        return $this->findOneBy(['user' => $user, 'publicId' => $publicId]);
    }
    public function findOneByDedupe(User $user, string $key): ?UserNotification
    {
        return $this->findOneBy(['user' => $user, 'dedupeKey' => $key]);
    }
}
