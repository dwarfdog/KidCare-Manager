<?php

namespace App\Controller;

use App\Entity\CareTemplate;
use App\Entity\Nanny;
use App\Form\CareTemplateType;
use App\Repository\CareTemplateRepository;
use App\Repository\NannyRepository;
use App\Service\ActivityLogger;
use App\Service\ApplicationSettings;
use App\Service\TemplateApplicationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/care-template', name: 'app_care_template_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class CareTemplateController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CareTemplateRepository $templateRepository): Response
    {
        return $this->render('care_template/index.html.twig', [
            'templates' => $templateRepository->findForUser($this->getUser()),
            'days' => CareTemplate::DAYS,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        CareTemplateRepository $templateRepository,
        NannyRepository $nannyRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
        ApplicationSettings $settings,
    ): Response {
        $template = new CareTemplate();
        $template->setUser($this->getUser());

        $form = $this->createForm(CareTemplateType::class, $template, [
            'nannies' => $nannyRepository->findAssignedToUser($this->getUser()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $template->setWeekSchedule($this->formatSchedule($request));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$template->getNanny()?->isAssignedTo($this->getUser())) {
                throw $this->createAccessDeniedException();
            }

            if ($this->hasDuplicateName($templateRepository, $template)) {
                return $this->render('care_template/new.html.twig', [
                    'template' => $template,
                    'form' => $form,
                    'days' => CareTemplate::DAYS,
                    'default_start' => $settings->defaultCareStart(),
                    'default_end' => $settings->defaultCareEnd(),
                    'default_meals' => $settings->defaultCareMeals(),
                ]);
            }

            if (!$this->validateTemplate($template)) {
                return $this->render('care_template/new.html.twig', [
                    'template' => $template,
                    'form' => $form,
                    'days' => CareTemplate::DAYS,
                    'default_start' => $settings->defaultCareStart(),
                    'default_end' => $settings->defaultCareEnd(),
                    'default_meals' => $settings->defaultCareMeals(),
                ]);
            }

            $entityManager->persist($template);
            $activityLogger->record($this->getUser(), 'template.create', 'Template cree.', [
                'template' => $template->getName(),
                'nanny' => $template->getNanny()?->getFullName(),
            ]);
            $entityManager->flush();
            $this->addFlash('success', 'Le template a ete cree.');

            return $this->redirectToRoute('app_care_template_index');
        }

        return $this->render('care_template/new.html.twig', [
            'template' => $template,
            'form' => $form,
            'days' => CareTemplate::DAYS,
            'default_start' => $settings->defaultCareStart(),
            'default_end' => $settings->defaultCareEnd(),
            'default_meals' => $settings->defaultCareMeals(),
        ]);
    }

    #[Route('/{publicId}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $publicId,
        Request $request,
        CareTemplateRepository $templateRepository,
        NannyRepository $nannyRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
        ApplicationSettings $settings,
    ): Response {
        $template = $templateRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$template instanceof CareTemplate) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(CareTemplateType::class, $template, [
            'nannies' => $nannyRepository->findAssignedToUser($this->getUser()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $template->setWeekSchedule($this->formatSchedule($request));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$template->getNanny()?->isAssignedTo($this->getUser())) {
                throw $this->createAccessDeniedException();
            }

            if ($this->hasDuplicateName($templateRepository, $template)) {
                return $this->render('care_template/edit.html.twig', [
                    'template' => $template,
                    'form' => $form,
                    'days' => CareTemplate::DAYS,
                    'default_start' => $settings->defaultCareStart(),
                    'default_end' => $settings->defaultCareEnd(),
                    'default_meals' => $settings->defaultCareMeals(),
                ]);
            }

            if (!$this->validateTemplate($template)) {
                return $this->render('care_template/edit.html.twig', [
                    'template' => $template,
                    'form' => $form,
                    'days' => CareTemplate::DAYS,
                    'default_start' => $settings->defaultCareStart(),
                    'default_end' => $settings->defaultCareEnd(),
                    'default_meals' => $settings->defaultCareMeals(),
                ]);
            }

            $activityLogger->record($this->getUser(), 'template.update', 'Template modifie.', [
                'template' => $template->getName(),
                'nanny' => $template->getNanny()?->getFullName(),
            ]);
            $entityManager->flush();
            $this->addFlash('success', 'Le template a ete modifie.');

            return $this->redirectToRoute('app_care_template_show', ['publicId' => $template->getPublicId()]);
        }

        return $this->render('care_template/edit.html.twig', [
            'template' => $template,
            'form' => $form,
            'days' => CareTemplate::DAYS,
            'default_start' => $settings->defaultCareStart(),
            'default_end' => $settings->defaultCareEnd(),
            'default_meals' => $settings->defaultCareMeals(),
        ]);
    }

    #[Route('/show/{publicId}', name: 'show', methods: ['GET'])]
    public function show(string $publicId, CareTemplateRepository $templateRepository): Response
    {
        $template = $templateRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$template instanceof CareTemplate) {
            throw $this->createNotFoundException();
        }

        return $this->render('care_template/show.html.twig', [
            'template' => $template,
            'days' => CareTemplate::DAYS,
        ]);
    }

    #[Route('/delete/{publicId}', name: 'delete', methods: ['POST'])]
    public function delete(
        string $publicId,
        Request $request,
        CareTemplateRepository $templateRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_template_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $template = $templateRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$template instanceof CareTemplate) {
            throw $this->createNotFoundException();
        }

        $activityLogger->record($this->getUser(), 'template.delete', 'Template supprime.', [
            'template' => $template->getName(),
            'nanny' => $template->getNanny()?->getFullName(),
        ]);
        $entityManager->remove($template);
        $entityManager->flush();
        $this->addFlash('success', 'Le template a ete supprime.');

        return $this->redirectToRoute('app_care_template_index');
    }

    #[Route('/duplicate/{publicId}', name: 'duplicate', methods: ['POST'])]
    public function duplicate(
        string $publicId,
        Request $request,
        CareTemplateRepository $templateRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('duplicate_template_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $template = $templateRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$template instanceof CareTemplate || !$template->getNanny() instanceof Nanny) {
            throw $this->createNotFoundException();
        }

        $copy = new CareTemplate();
        $copy
            ->setUser($this->getUser())
            ->setNanny($template->getNanny())
            ->setName($this->uniqueCopyName($templateRepository, $template))
            ->setWeekSchedule($template->getWeekSchedule());

        $entityManager->persist($copy);
        $activityLogger->record($this->getUser(), 'template.duplicate', 'Template duplique.', [
            'source' => $template->getPublicId(),
            'copy' => $copy->getName(),
        ]);
        $entityManager->flush();
        $this->addFlash('success', 'Le template a ete duplique.');

        return $this->redirectToRoute('app_care_template_edit', ['publicId' => $copy->getPublicId()]);
    }

    #[Route('/preview/{publicId}', name: 'preview', methods: ['POST'])]
    public function preview(
        string $publicId,
        Request $request,
        CareTemplateRepository $templateRepository,
        TemplateApplicationService $applicationService,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('apply_template_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $template = $templateRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$template instanceof CareTemplate || !$template->getNanny() instanceof Nanny) {
            throw $this->createNotFoundException();
        }

        try {
            $result = $applicationService->preview($this->getUser(), $template, $this->weekStartFromRequest($request));
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }

        return $this->json($this->applicationPayload($result));
    }

    #[Route('/apply/{publicId}', name: 'apply', methods: ['POST'])]
    public function apply(
        string $publicId,
        Request $request,
        CareTemplateRepository $templateRepository,
        TemplateApplicationService $applicationService,
    ): Response {
        if (!$this->isCsrfTokenValid('apply_template_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $template = $templateRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$template instanceof CareTemplate || !$template->getNanny() instanceof Nanny) {
            throw $this->createNotFoundException();
        }

        $weekStart = $this->weekStartFromRequest($request);

        try {
            $result = $applicationService->apply($this->getUser(), $template, $weekStart);
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_care_index', [
                'publicId' => $template->getNanny()->getPublicId(),
                'currentDate' => $weekStart->format('Y-m-d'),
            ]);
        }

        if (count($result['accepted']) > 0) {
            $this->addFlash('success', sprintf('%d creneau(x) applique(s).', count($result['accepted'])));
        }
        if (count($result['rejected']) > 0) {
            $this->addFlash('warning', sprintf('%d creneau(x) refuse(s).', count($result['rejected'])));
        }
        if (count($result['accepted']) === 0 && count($result['rejected']) === 0) {
            $this->addFlash('warning', 'Aucun creneau actif dans ce template.');
        }

        return $this->redirectToRoute('app_care_index', [
            'publicId' => $template->getNanny()->getPublicId(),
            'currentDate' => $weekStart->format('Y-m-d'),
        ]);
    }

    private function formatSchedule(Request $request): array
    {
        $weekSchedule = $request->request->all('weekSchedule');
        $formatted = CareTemplate::emptyWeekSchedule();

        foreach (CareTemplate::DAYS as $day => $label) {
            $config = $weekSchedule[$day] ?? [];
            $isActive = filter_var($config['isActive'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $formatted[$day] = [
                'isActive' => $isActive,
                'slots' => !$isActive ? [] : array_map(static fn (array $slot): array => [
                    'startTime' => (string) ($slot['startTime'] ?? ''),
                    'endTime' => (string) ($slot['endTime'] ?? ''),
                    'mealsCount' => max(0, (int) ($slot['mealsCount'] ?? 0)),
                ], $config['slots'] ?? []),
            ];
        }

        return $formatted;
    }

    private function validateTemplate(CareTemplate $template): bool
    {
        foreach ($template->getWeekSchedule() as $day => $config) {
            if (!($config['isActive'] ?? false)) {
                continue;
            }

            $label = CareTemplate::DAYS[$day] ?? $day;
            if (empty($config['slots'])) {
                $this->addFlash('error', sprintf('%s doit contenir au moins un creneau actif.', $label));

                return false;
            }

            $intervals = [];
            foreach ($config['slots'] as $slot) {
                if (($slot['startTime'] ?? '') === '' || ($slot['endTime'] ?? '') === '') {
                    $this->addFlash('error', sprintf('%s contient un horaire incomplet.', $label));

                    return false;
                }

                $start = new \DateTimeImmutable('1970-01-01 ' . $slot['startTime']);
                $end = new \DateTimeImmutable('1970-01-01 ' . $slot['endTime']);
                if ($end <= $start) {
                    $end = $end->modify('+1 day');
                }

                $intervals[] = [$start, $end];
            }

            usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
            for ($i = 0; $i < count($intervals) - 1; $i++) {
                if ($intervals[$i][1] > $intervals[$i + 1][0]) {
                    $this->addFlash('error', sprintf('%s contient des creneaux qui se chevauchent.', $label));

                    return false;
                }
            }
        }

        return true;
    }

    private function hasDuplicateName(CareTemplateRepository $templateRepository, CareTemplate $template): bool
    {
        $nanny = $template->getNanny();
        if (!$nanny instanceof Nanny) {
            return false;
        }

        if (!$templateRepository->existsForUserNannyAndName($this->getUser(), $nanny, (string) $template->getName(), $template)) {
            return false;
        }

        $this->addFlash('error', 'Un template avec ce nom existe deja pour cette nounou.');

        return true;
    }

    private function weekStartFromRequest(Request $request): \DateTimeImmutable
    {
        return (new \DateTimeImmutable((string) $request->request->get('start')))->modify('monday this week')->setTime(0, 0);
    }

    private function applicationPayload(array $result): array
    {
        return [
            'week_start' => $result['weekStart'],
            'accepted_count' => count($result['accepted']),
            'rejected_count' => count($result['rejected']),
            'conflicts' => $result['rejected'],
        ];
    }

    private function uniqueCopyName(CareTemplateRepository $templateRepository, CareTemplate $template): string
    {
        $base = (string) $template->getName() . ' copie';
        $name = $base;
        $index = 2;

        while ($templateRepository->existsForUserNannyAndName($this->getUser(), $template->getNanny(), $name)) {
            $name = $base . ' ' . $index;
            $index++;
        }

        return $name;
    }
}
