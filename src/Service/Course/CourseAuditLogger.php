<?php

namespace App\Service\Course;

use App\Entity\CourseAuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class CourseAuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(?User $user, string $action, string $entityType, ?string $entityPublicId = null, array $context = []): void
    {
        $log = new CourseAuditLog();
        $log
            ->setUser($user)
            ->setAction($action)
            ->setEntity($entityType, $entityPublicId)
            ->setContext($context);

        $this->entityManager->persist($log);
        $this->logger->info('Courses audit: ' . $action, [
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'actor' => $user?->getUserIdentifier(),
        ] + $context);
    }
}
