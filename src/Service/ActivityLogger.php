<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ActivityLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(?User $user, string $action, string $message, array $context = []): void
    {
        $activity = new ActivityLog();
        $activity
            ->setUser($user)
            ->setActorIdentifier($user?->getUserIdentifier())
            ->setAction($action)
            ->setMessage($message)
            ->setContext($context);

        $this->entityManager->persist($activity);
        $this->logger->info($message, ['action' => $action, 'actor' => $user?->getUserIdentifier()] + $context);
    }
}
