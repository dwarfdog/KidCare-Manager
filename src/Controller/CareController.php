<?php

namespace App\Controller;

use App\Entity\Care;
use App\Entity\Nanny;
use App\Repository\CareRepository;
use App\Repository\CareTemplateRepository;
use App\Repository\NannyRepository;
use App\Service\ApplicationSettings;
use App\Service\CareManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/care', name: 'app_care_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class CareController extends AbstractController
{
    #[Route('/planning/{publicId?}/{currentDate?}', name: 'index', methods: ['GET'])]
    public function index(
        CareRepository $careRepository,
        CareTemplateRepository $careTemplateRepository,
        NannyRepository $nannyRepository,
        ApplicationSettings $settings,
        ?string $publicId = null,
        ?string $currentDate = null,
    ): Response {
        $user = $this->getUser();
        $nannies = $nannyRepository->findAssignedToUser($user);
        $nanny = null;

        if ($publicId !== null) {
            $nanny = $nannyRepository->findOneByPublicId($publicId);
            if (!$nanny instanceof Nanny || !$nanny->isAssignedTo($user)) {
                $this->addFlash('warning', 'Vous n\'avez pas acces a cette nounou.');

                return $this->redirectToRoute('app_care_index');
            }
        }

        $events = $nanny instanceof Nanny
            ? array_map(fn (Care $care): array => $this->eventPayload($care), $careRepository->findForUserAndNanny($user, $nanny))
            : [];

        $templates = $nanny instanceof Nanny ? $careTemplateRepository->findByUserAndNanny($user, $nanny) : [];
        $date = $this->isValidDate($currentDate) ? new \DateTime($currentDate) : new \DateTime();

        return $this->render('care/index.html.twig', [
            'nannies' => $nannies,
            'selected_nanny' => $nanny,
            'currentDate' => $date->format('Y-m-d'),
            'events' => $events,
            'templates' => $templates,
            'csrf_token' => $this->container->get('security.csrf.token_manager')->getToken('care_mutation')->getValue(),
            'default_start' => $settings->defaultCareStart(),
            'default_end' => $settings->defaultCareEnd(),
            'default_meals' => $settings->defaultCareMeals(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        NannyRepository $nannyRepository,
        CareManager $careManager,
    ): JsonResponse {
        $this->assertValidCareCsrf($request);
        $payload = $this->jsonPayload($request);

        $nanny = $nannyRepository->findOneByPublicId((string) ($payload['nanny'] ?? ''));
        if (!$nanny instanceof Nanny || !$nanny->isAssignedTo($this->getUser())) {
            return $this->json(['error' => 'La nounou n\'est pas valide.'], 400);
        }

        try {
            $segments = $careManager->create(
                $this->getUser(),
                $nanny,
                $this->localDateTimeFromPayload($payload, 'start'),
                $this->localDateTimeFromPayload($payload, 'end'),
                $this->mealsFromPayload($payload),
            );
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }

        return $this->json(['events' => array_map(fn (Care $care): array => $this->eventPayload($care), $segments)], 201);
    }

    #[Route('/update/{publicId}', name: 'update', methods: ['PATCH'])]
    public function update(
        string $publicId,
        Request $request,
        CareRepository $careRepository,
        CareManager $careManager,
    ): JsonResponse {
        $this->assertValidCareCsrf($request);
        $care = $careRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$care instanceof Care) {
            return $this->json(['error' => 'La garde est introuvable.'], 404);
        }

        $payload = $this->jsonPayload($request);
        try {
            $segments = $careManager->replace(
                $care,
                $this->localDateTimeFromPayload($payload, 'start'),
                $this->localDateTimeFromPayload($payload, 'end'),
                $this->mealsFromPayload($payload),
            );
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }

        return $this->json([
            'removed' => [$publicId],
            'events' => array_map(fn (Care $segment): array => $this->eventPayload($segment), $segments),
        ]);
    }

    #[Route('/delete/{publicId}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        string $publicId,
        Request $request,
        CareRepository $careRepository,
        CareManager $careManager,
    ): JsonResponse {
        $this->assertValidCareCsrf($request);
        $care = $careRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$care instanceof Care) {
            return $this->json(['error' => 'La garde est introuvable.'], 404);
        }

        try {
            $careManager->delete($care);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }

        return $this->json(['success' => true]);
    }

    private function eventPayload(Care $care): array
    {
        $description = sprintf(
            'De %s a %s%s',
            $care->getStartDateTime()->format('H:i'),
            $care->getEndDateTime()->format('H:i'),
            $care->getMealsCount() > 0 ? sprintf('<br>%d repas', $care->getMealsCount()) : ''
        );

        return [
            'id' => $care->getPublicId(),
            'title' => number_format($care->getHoursCount(), 2, ',', ' ') . ' heures',
            'start' => $this->formatCalendarDateTime($care->getStartDateTime()),
            'end' => $this->formatCalendarDateTime($care->getEndDateTime()),
            'meals' => $care->getMealsCount(),
            'description' => $description,
            'segment' => $care->isMultiSegment() ? sprintf('%d/%d', $care->getSegmentIndex(), $care->getSegmentTotal()) : null,
        ];
    }

    private function localDateTimeFromPayload(array $payload, string $key): \DateTimeImmutable
    {
        $value = trim((string) ($payload[$key] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value) !== 1) {
            throw new \InvalidArgumentException('Horaire invalide.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException('Horaire invalide.');
        }

        return $date;
    }

    private function formatCalendarDateTime(\DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d\TH:i:s');
    }

    private function assertValidCareCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('care_mutation', (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
    }

    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private function mealsFromPayload(array $payload): int
    {
        return max(0, (int) ($payload['meals'] ?? 0));
    }

    private function isValidDate(?string $currentDate): bool
    {
        if ($currentDate === null || trim($currentDate) === '') {
            return false;
        }

        try {
            $date = new \DateTime($currentDate);

            return $date->format('Y-m-d') === $currentDate;
        } catch (\Throwable) {
            return false;
        }
    }
}
