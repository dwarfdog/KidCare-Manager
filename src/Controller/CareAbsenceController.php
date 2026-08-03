<?php

namespace App\Controller;

use App\Entity\CareAbsence;
use App\Entity\Nanny;
use App\Repository\CareAbsenceRepository;
use App\Repository\CareRepository;
use App\Repository\NannyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/care/absences', name: 'app_care_absence_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class CareAbsenceController extends AbstractController
{
    #[Route('/{publicId}', name: 'index', methods: ['GET', 'POST'])]
    public function index(string $publicId, Request $request, NannyRepository $nannies, CareAbsenceRepository $absences, CareRepository $cares, EntityManagerInterface $entityManager): Response
    {
        $nanny = $this->nanny($publicId, $nannies);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('care_absence_create_'.$publicId, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException('Token CSRF invalide.'); }
            try {
                $startsAt = $this->dateTime((string) $request->request->get('starts_at'));
                $endsAt = $this->dateTime((string) $request->request->get('ends_at'));
                if ($endsAt <= $startsAt) { throw new \InvalidArgumentException('La fin doit être postérieure au début.'); }
                $absence = (new CareAbsence())->setUser($this->getUser())->setNanny($nanny)->setType((string) $request->request->get('type'))
                    ->setStartsAt($startsAt)->setEndsAt($endsAt)->setRemunerated($request->request->getBoolean('remunerated'))->setNotes($request->request->get('notes'));
                $entityManager->persist($absence);
                $entityManager->flush();
                $conflicts = array_filter($cares->findBetween($this->getUser(), $nanny, $startsAt, $endsAt), static fn ($care): bool => !$care->isCancelled() && $care->getStartDateTime() < $endsAt && $care->getEndDateTime() > $startsAt);
                $this->addFlash($conflicts === [] ? 'success' : 'warning', $conflicts === [] ? 'Indisponibilité enregistrée.' : sprintf('Indisponibilité enregistrée avec %d conflit(s) de garde à traiter.', count($conflicts)));
                return $this->redirectToRoute('app_care_absence_index', ['publicId' => $publicId]);
            } catch (\Throwable $exception) { $this->addFlash('error', $exception->getMessage()); }
        }
        $rows = [];
        foreach ($absences->findActiveFor($this->getUser(), $nanny) as $absence) {
            $rows[] = ['absence' => $absence, 'conflicts' => count(array_filter($cares->findBetween($this->getUser(), $nanny, $absence->getStartsAt(), $absence->getEndsAt()), static fn ($care): bool => !$care->isCancelled() && $care->getStartDateTime() < $absence->getEndsAt() && $care->getEndDateTime() > $absence->getStartsAt()))];
        }
        return $this->render('care/absences.html.twig', ['nanny' => $nanny, 'rows' => $rows, 'types' => CareAbsence::typeLabels()]);
    }

    #[Route('/{nannyId}/{absenceId}/archive', name: 'archive', methods: ['POST'])]
    public function archive(string $nannyId, string $absenceId, Request $request, NannyRepository $nannies, CareAbsenceRepository $absences, EntityManagerInterface $entityManager): Response
    {
        $nanny = $this->nanny($nannyId, $nannies);
        if (!$this->isCsrfTokenValid('care_absence_archive_'.$absenceId, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }
        $absence = $absences->findOneForUser($this->getUser(), $absenceId);
        if (!$absence || $absence->getNanny() !== $nanny) { throw $this->createNotFoundException(); }
        $absence->archive(); $entityManager->flush(); $this->addFlash('warning', 'Indisponibilité archivée pour mémoire.');
        return $this->redirectToRoute('app_care_absence_index', ['publicId' => $nannyId]);
    }

    private function nanny(string $publicId, NannyRepository $repository): Nanny { $nanny = $repository->findOneByPublicId($publicId); if (!$nanny || !$nanny->isAssignedTo($this->getUser())) { throw $this->createNotFoundException(); } return $nanny; }
    private function dateTime(string $value): \DateTimeImmutable { $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value); if (!$date) { throw new \InvalidArgumentException('Date ou heure invalide.'); } return $date; }
}
