<?php

namespace App\Controller;

use App\Entity\Nanny;
use App\Entity\NannyRate;
use App\Form\NannyRateType;
use App\Form\NannyType;
use App\Repository\NannyRepository;
use App\Repository\NannyRateRepository;
use App\Service\ActivityLogger;
use App\Service\ApplicationSettings;
use App\Service\NannyManager;
use App\Service\NannyRateManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/nanny', name: 'app_nanny_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class NannyController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        NannyRepository $nannyRepository,
        NannyRateRepository $rateRepository,
        ApplicationSettings $settings,
    ): Response {
        $user = $this->getUser();
        $nannies = $nannyRepository->findAssignedToUser($user);
        $ratesByNanny = [];

        foreach ($nannies as $nanny) {
            $ratesByNanny[$nanny->getPublicId()] = $rateRepository->findForUserAndNanny($user, $nanny);
        }

        return $this->render('nanny/index.html.twig', [
            'nannies' => $nannies,
            'rates_by_nanny' => $ratesByNanny,
            'search_results' => $nannyRepository->searchAttachable($user, $request->query->get('q')),
            'query' => $request->query->get('q', ''),
            'currency' => $settings->currency(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        NannyManager $nannyManager,
        EntityManagerInterface $entityManager,
    ): Response {
        $nanny = new Nanny();
        $form = $this->createForm(NannyType::class, $nanny);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $nannyManager->createAndAttach($this->getUser(), $nanny);
                $entityManager->flush();
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->render('nanny/new.html.twig', ['form' => $form]);
            }

            $this->addFlash('success', 'La nounou a ete creee et rattachee a votre compte.');

            return $this->redirectToRoute('app_nanny_index');
        }

        return $this->render('nanny/new.html.twig', ['form' => $form]);
    }

    #[Route('/{publicId}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $publicId,
        Request $request,
        NannyRepository $nannyRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
    ): Response {
        $nanny = $this->getEditableNanny($publicId, $nannyRepository);
        $form = $this->createForm(NannyType::class, $nanny);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($nannyRepository->findDuplicateIdentity($nanny) !== null) {
                $this->addFlash('error', 'Une nounou avec la meme identite et adresse existe deja.');

                return $this->render('nanny/edit.html.twig', ['nanny' => $nanny, 'form' => $form]);
            }

            $activityLogger->record($this->getUser(), 'nanny.update', 'Informations nounou modifiees.', [
                'nanny' => $nanny->getFullName(),
            ]);
            $entityManager->flush();
            $this->addFlash('success', 'Les informations communes ont ete mises a jour.');

            return $this->redirectToRoute('app_nanny_index');
        }

        return $this->render('nanny/edit.html.twig', ['nanny' => $nanny, 'form' => $form]);
    }

    #[Route('/attach/{publicId}', name: 'attach', methods: ['POST'])]
    public function attach(
        string $publicId,
        Request $request,
        NannyRepository $nannyRepository,
        NannyManager $nannyManager,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('attach_nanny_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $nanny = $nannyRepository->findOneByPublicId($publicId);
        if (!$nanny instanceof Nanny) {
            throw $this->createNotFoundException();
        }

        $nannyManager->attach($this->getUser(), $nanny);
        $entityManager->flush();
        $this->addFlash('success', 'La nounou a ete rattachee a votre compte.');

        return $this->redirectToRoute('app_nanny_index');
    }

    #[Route('/attach-by-code', name: 'attach_by_code', methods: ['POST'])]
    public function attachByCode(
        Request $request,
        NannyRepository $nannyRepository,
        NannyManager $nannyManager,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('attach_nanny_by_code', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $nanny = $nannyRepository->findOneByInviteCode((string) $request->request->get('invite_code'));
        if (!$nanny instanceof Nanny) {
            $this->addFlash('error', 'Code de rattachement introuvable.');

            return $this->redirectToRoute('app_nanny_index');
        }

        $nannyManager->attach($this->getUser(), $nanny);
        $entityManager->flush();
        $this->addFlash('success', 'La nounou a ete rattachee via le code.');

        return $this->redirectToRoute('app_nanny_index');
    }

    #[Route('/{publicId}/rate/new', name: 'rate_new', methods: ['GET', 'POST'])]
    public function newRate(
        string $publicId,
        Request $request,
        NannyRepository $nannyRepository,
        NannyRateManager $rateManager,
    ): Response {
        $nanny = $this->getAssignedNanny($publicId, $nannyRepository);
        $rate = new NannyRate();
        $rate->setUser($this->getUser())->setNanny($nanny);

        $form = $this->createForm(NannyRateType::class, $rate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $rateManager->add($this->getUser(), $nanny, $rate);
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->render('nanny/rate_new.html.twig', ['nanny' => $nanny, 'form' => $form]);
            }

            $this->addFlash('success', 'Le tarif historique a ete ajoute.');

            return $this->redirectToRoute('app_nanny_index');
        }

        return $this->render('nanny/rate_new.html.twig', ['nanny' => $nanny, 'form' => $form]);
    }

    #[Route('/{publicId}/rate/{ratePublicId}/edit', name: 'rate_edit', methods: ['GET', 'POST'])]
    public function editRate(
        string $publicId,
        string $ratePublicId,
        Request $request,
        NannyRepository $nannyRepository,
        NannyRateRepository $rateRepository,
        NannyRateManager $rateManager,
    ): Response {
        $nanny = $this->getAssignedNanny($publicId, $nannyRepository);
        $rate = $this->getRateForNanny($ratePublicId, $nanny, $rateRepository);
        $previousEffectiveFrom = \DateTimeImmutable::createFromInterface($rate->getEffectiveFrom());
        $rate->setNanny($nanny)->setUser($this->getUser());

        $form = $this->createForm(NannyRateType::class, $rate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $rateManager->update($this->getUser(), $nanny, $rate, $previousEffectiveFrom);
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->render('nanny/rate_edit.html.twig', ['nanny' => $nanny, 'rate' => $rate, 'form' => $form]);
            }

            $this->addFlash('success', 'Le tarif historique a ete modifie.');

            return $this->redirectToRoute('app_nanny_index');
        }

        return $this->render('nanny/rate_edit.html.twig', ['nanny' => $nanny, 'rate' => $rate, 'form' => $form]);
    }

    #[Route('/{publicId}/rate/{ratePublicId}/delete', name: 'rate_delete', methods: ['POST'])]
    public function deleteRate(
        string $publicId,
        string $ratePublicId,
        Request $request,
        NannyRepository $nannyRepository,
        NannyRateRepository $rateRepository,
        NannyRateManager $rateManager,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_rate_' . $ratePublicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $nanny = $this->getAssignedNanny($publicId, $nannyRepository);
        $rate = $this->getRateForNanny($ratePublicId, $nanny, $rateRepository);
        $rate->setNanny($nanny)->setUser($this->getUser());

        try {
            $rateManager->delete($this->getUser(), $nanny, $rate);
            $this->addFlash('success', 'Le tarif historique a ete supprime.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_nanny_index');
    }

    #[Route('/{publicId}/detach', name: 'detach', methods: ['POST'])]
    public function detach(
        string $publicId,
        Request $request,
        NannyRepository $nannyRepository,
        NannyManager $nannyManager,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('detach_nanny_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $nanny = $this->getAssignedNanny($publicId, $nannyRepository);

        try {
            $nannyManager->detach($this->getUser(), $nanny);
            $entityManager->flush();
            $this->addFlash('success', 'La nounou a ete detachee. L\'historique paye reste consultable.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_nanny_index');
    }

    private function getAssignedNanny(string $publicId, NannyRepository $nannyRepository): Nanny
    {
        $nanny = $nannyRepository->findOneByPublicId($publicId);
        if (!$nanny instanceof Nanny || !$nanny->isAssignedTo($this->getUser())) {
            throw $this->createNotFoundException();
        }

        return $nanny;
    }

    private function getEditableNanny(string $publicId, NannyRepository $nannyRepository): Nanny
    {
        $nanny = $this->getAssignedNanny($publicId, $nannyRepository);
        if ($nanny->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Seul le createur initial peut modifier les donnees communes.');
        }

        return $nanny;
    }

    private function getRateForNanny(string $ratePublicId, Nanny $nanny, NannyRateRepository $rateRepository): NannyRate
    {
        $rate = $rateRepository->findOneForUserByPublicId($this->getUser(), $ratePublicId);
        if (!$rate instanceof NannyRate || $rate->getNannySnapshotKey() !== $nanny->getPublicId()) {
            throw $this->createNotFoundException();
        }

        return $rate;
    }
}
