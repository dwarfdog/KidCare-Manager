<?php

namespace App\Controller;

use App\Entity\CalendarSubscription;
use App\Entity\Nanny;
use App\Repository\CalendarSubscriptionRepository;
use App\Repository\NannyRepository;
use App\Service\CareCalendarExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CareCalendarController extends AbstractController
{
    #[Route('/care/calendar/{publicId}', name: 'app_care_calendar_manage', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function manage(string $publicId, NannyRepository $nannies, CalendarSubscriptionRepository $subscriptions, EntityManagerInterface $entityManager): Response
    {
        $nanny = $this->nanny($publicId, $nannies); $subscription = $subscriptions->findFor($this->getUser(), $nanny);
        if (!$subscription) { $subscription = (new CalendarSubscription())->setUser($this->getUser())->setNanny($nanny); $entityManager->persist($subscription); $entityManager->flush(); }
        return $this->render('care/calendar.html.twig', ['nanny' => $nanny, 'subscription' => $subscription]);
    }

    #[Route('/care/calendar/{publicId}/download', name: 'app_care_calendar_download', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function download(string $publicId, NannyRepository $nannies, CareCalendarExporter $exporter): Response { return $this->ics($exporter->export($this->getUser(), $this->nanny($publicId, $nannies)), 'kidcare-'.$publicId.'.ics'); }

    #[Route('/care/calendar/{publicId}/regenerate', name: 'app_care_calendar_regenerate', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function regenerate(string $publicId, Request $request, NannyRepository $nannies, CalendarSubscriptionRepository $subscriptions, EntityManagerInterface $entityManager): Response
    {
        $nanny = $this->nanny($publicId, $nannies); if (!$this->isCsrfTokenValid('calendar_regenerate_'.$publicId, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }
        $subscription = $subscriptions->findFor($this->getUser(), $nanny); if (!$subscription) { $subscription = (new CalendarSubscription())->setUser($this->getUser())->setNanny($nanny); $entityManager->persist($subscription); } else { $subscription->regenerate(); }
        $entityManager->flush(); $this->addFlash('success', 'Adresse privée révoquée et régénérée.'); return $this->redirectToRoute('app_care_calendar_manage', ['publicId' => $publicId]);
    }

    #[Route('/calendar/feed/{token}.ics', name: 'app_care_calendar_feed', methods: ['GET'])]
    public function feed(string $token, CalendarSubscriptionRepository $subscriptions, CareCalendarExporter $exporter): Response
    {
        $subscription = $subscriptions->findOneByToken($token); if (!$subscription || !$subscription->getUser() || !$subscription->getNanny()) { throw $this->createNotFoundException(); }
        return $this->ics($exporter->export($subscription->getUser(), $subscription->getNanny()), 'kidcare.ics');
    }

    private function nanny(string $id, NannyRepository $repository): Nanny { $nanny = $repository->findOneByPublicId($id); if (!$nanny || !$nanny->isAssignedTo($this->getUser())) { throw $this->createNotFoundException(); } return $nanny; }
    private function ics(string $content, string $filename): Response { $response = new Response($content); $response->headers->set('Content-Type', 'text/calendar; charset=utf-8'); $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"'); return $response; }
}
