<?php

namespace App\Service;

use App\Entity\Care;
use App\Entity\CareTemplate;
use App\Entity\Nanny;
use App\Entity\User;
use App\Repository\CareRepository;
use App\Repository\NannyRateRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TemplateApplicationService
{
    public function __construct(
        private readonly CareSegmenter $segmenter,
        private readonly CareRepository $careRepository,
        private readonly NannyRateRepository $rateRepository,
        private readonly MonthlyPaymentCalculator $paymentCalculator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function preview(User $user, CareTemplate $template, \DateTimeImmutable $weekStart): array
    {
        return $this->evaluate($user, $template, $weekStart, false);
    }

    public function apply(User $user, CareTemplate $template, \DateTimeImmutable $weekStart): array
    {
        $result = $this->evaluate($user, $template, $weekStart, true);
        $nanny = $template->getNanny();
        if (!$nanny instanceof Nanny || $result['accepted'] === []) {
            return $result;
        }

        $this->entityManager->wrapInTransaction(function () use ($user, $nanny, $template, &$result): void {
            $months = [];
            $created = [];

            foreach ($result['accepted'] as $candidate) {
                foreach ($candidate['segments'] as $segment) {
                    $this->entityManager->persist($segment);
                    $created[] = $segment;
                    $months[$segment->getMonth() . '|' . $nanny->getPublicId()] = $nanny;
                }
            }

            $this->entityManager->flush();
            $this->paymentCalculator->recalculateMany($user, $months);
            $this->activityLogger->record($user, 'template.apply', 'Template applique.', [
                'template' => $template->getPublicId(),
                'nanny' => $nanny->getFullName(),
                'created' => count($created),
                'rejected' => count($result['rejected']),
            ]);
            $this->entityManager->flush();

            $result['events'] = array_map(fn (Care $care): array => [
                'id' => $care->getPublicId(),
                'date' => $care->getDate()->format('Y-m-d'),
            ], $created);
        });

        return $result;
    }

    private function evaluate(User $user, CareTemplate $template, \DateTimeImmutable $weekStart, bool $withSegments): array
    {
        $nanny = $template->getNanny();
        if (!$nanny instanceof Nanny || !$nanny->isAssignedTo($user)) {
            throw new \DomainException('Template introuvable pour cette nounou.');
        }

        $weekStart = $weekStart->modify('monday this week')->setTime(0, 0);
        $schedule = $template->getWeekSchedule();
        $offsets = array_flip(array_keys(CareTemplate::DAYS));
        $acceptedIntervals = [];
        $accepted = [];
        $rejected = [];

        foreach ($schedule as $day => $config) {
            if (!($config['isActive'] ?? false)) {
                continue;
            }

            $date = $weekStart->modify('+' . ($offsets[$day] ?? 0) . ' days');
            foreach ($config['slots'] ?? [] as $slot) {
                $candidate = $this->candidate($user, $nanny, $date, $slot);
                $reason = $this->rejectionReason($user, $nanny, $candidate['segments'], $candidate['start'], $candidate['end'], $acceptedIntervals);

                if ($reason !== null) {
                    $rejected[] = [
                        'label' => $candidate['label'],
                        'reason' => $reason,
                    ];
                    continue;
                }

                $acceptedIntervals[] = [$candidate['start'], $candidate['end']];
                $accepted[] = [
                    'label' => $candidate['label'],
                    'date' => $candidate['start']->format('Y-m-d'),
                    'startTime' => $candidate['start']->format('H:i'),
                    'endTime' => $candidate['end']->format('H:i'),
                    'segments' => $withSegments ? $candidate['segments'] : [],
                ];
            }
        }

        return [
            'weekStart' => $weekStart->format('Y-m-d'),
            'accepted' => $accepted,
            'rejected' => $rejected,
            'events' => [],
        ];
    }

    private function candidate(User $user, Nanny $nanny, \DateTimeImmutable $date, array $slot): array
    {
        $start = new \DateTimeImmutable($date->format('Y-m-d') . ' ' . (string) ($slot['startTime'] ?? ''));
        $end = new \DateTimeImmutable($date->format('Y-m-d') . ' ' . (string) ($slot['endTime'] ?? ''));
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        $segments = $this->segmenter->split($user, $nanny, $start, $end, max(0, (int) ($slot['mealsCount'] ?? 0)));

        return [
            'start' => $start,
            'end' => $end,
            'segments' => $segments,
            'label' => sprintf('%s %s-%s', $start->format('d/m/Y'), $start->format('H:i'), $end->format('H:i')),
        ];
    }

    private function rejectionReason(User $user, Nanny $nanny, array $segments, \DateTimeImmutable $start, \DateTimeImmutable $end, array $acceptedIntervals): ?string
    {
        foreach ($acceptedIntervals as [$acceptedStart, $acceptedEnd]) {
            if ($acceptedStart < $end && $acceptedEnd > $start) {
                return 'Conflit avec un autre creneau valide du template.';
            }
        }

        foreach ($segments as $segment) {
            try {
                $this->paymentCalculator->assertMonthEditable($user, $nanny, $segment->getMonth());
            } catch (\DomainException $exception) {
                return $exception->getMessage();
            }

            if ($this->careRepository->overlaps($user, $nanny, $segment->getStartDateTime(), $segment->getEndDateTime())) {
                return 'Conflit avec une garde existante.';
            }

            if ($this->rateRepository->findApplicableRate($user, $nanny, $segment->getDate()) === null) {
                return sprintf('Aucun tarif configure pour le %s.', $segment->getDate()->format('d/m/Y'));
            }
        }

        return null;
    }
}
