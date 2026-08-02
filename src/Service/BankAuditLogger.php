<?php

namespace App\Service;

use App\Entity\BankAuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class BankAuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(?User $user, string $action, string $entityType, ?string $entityPublicId = null, array $context = []): void
    {
        $log = new BankAuditLog();
        $log
            ->setUser($user)
            ->setAction($action)
            ->setEntity($entityType, $entityPublicId)
            ->setContext($context);

        $this->entityManager->persist($log);
        $this->logger->info('Bank audit: ' . $action, [
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'actor' => $user?->getUserIdentifier(),
        ] + $context);
    }
}
