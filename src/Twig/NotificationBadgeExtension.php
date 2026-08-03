<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\UserNotificationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationBadgeExtension extends AbstractExtension
{
    public function __construct(private readonly UserNotificationRepository $notifications)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_badge', $this->badge(...)),
        ];
    }

    /** @return array{count:int, severity:?string} */
    public function badge(?User $user): array
    {
        if ($user === null) {
            return ['count' => 0, 'severity' => null];
        }

        return $this->notifications->unreadSummary($user);
    }
}
