<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\CareStatusSynchronizer;
use App\Service\InternalNotificationGenerator;
use App\Service\NannyOwnershipService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NannyOwnershipService $ownerships,
        private readonly CareStatusSynchronizer $careStatuses,
        private readonly InternalNotificationGenerator $notifications,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }
        // A returning owner is active again before the global inactivity audit.
        $user->setLastLoginAt(new \DateTime());
        $this->entityManager->flush();
        $this->ownerships->transferInactiveOwnerships();
        $this->careStatuses->synchronize($user);
        $this->notifications->generateFor($user);
        $this->entityManager->flush();
    }
}
