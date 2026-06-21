<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmailVerificationGuard implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = [
        'app_verification_required',
        'app_verify_email',
        'app_resend_verification',
        'app_account_profile',
        'app_account_password',
        'app_account_delete',
        'app_logout',
        'app_login',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly EmailVerificationService $verificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 8]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->verificationService->isRequired()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->verificationService->graceExpired($user)) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route');
        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_verification_required')));
    }
}
