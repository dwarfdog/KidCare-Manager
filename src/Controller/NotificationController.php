<?php

namespace App\Controller;

use App\Repository\UserNotificationRepository;
use App\Service\InternalNotificationGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications', name: 'app_notification_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class NotificationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UserNotificationRepository $repository, InternalNotificationGenerator $generator): Response { $generator->generateFor($this->getUser()); return $this->render('notification/index.html.twig', ['notifications' => $repository->findForUser($this->getUser())]); }
    #[Route('/{publicId}/read', name: 'read', methods: ['POST'])]
    public function read(string $publicId, Request $request, UserNotificationRepository $repository, EntityManagerInterface $entityManager): Response { if (!$this->isCsrfTokenValid('notification_read_'.$publicId, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); } $notification = $repository->findOneForUser($this->getUser(), $publicId); if (!$notification) { throw $this->createNotFoundException(); } $notification->markRead(); $entityManager->flush(); return $this->redirect($notification->getLink() ?: $this->generateUrl('app_notification_index')); }
    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    public function readAll(Request $request, UserNotificationRepository $repository, EntityManagerInterface $entityManager): Response { if (!$this->isCsrfTokenValid('notifications_read_all', (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); } foreach ($repository->findUnreadForUser($this->getUser(), 500) as $notification) { $notification->markRead(); } $entityManager->flush(); return $this->redirectToRoute('app_notification_index'); }
}
