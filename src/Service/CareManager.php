<?php

namespace App\Service;

use App\Entity\Care;
use App\Entity\Nanny;
use App\Entity\User;
use App\Repository\CareRepository;
use App\Repository\NannyRateRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CareManager
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

    /** @return Care[] */
    public function create(User $user, Nanny $nanny, \DateTimeImmutable $start, \DateTimeImmutable $end, int $meals): array
    {
        $segments = $this->segmenter->split($user, $nanny, $start, $end, $meals);
        $this->assertSegmentsCanBeSaved($user, $nanny, $segments);

        foreach ($segments as $segment) {
            $this->entityManager->persist($segment);
        }

        $this->entityManager->flush();
        $this->recalculateSegments($user, $segments);
        $this->activityLogger->record($user, 'care.create', 'Garde creee.', [
            'nanny' => $nanny->getFullName(),
            'segments' => count($segments),
            'start' => $start->format(\DateTimeInterface::ATOM),
            'end' => $end->format(\DateTimeInterface::ATOM),
        ]);
        $this->entityManager->flush();

        return $segments;
    }

    /** @return Care[] */
    public function replace(Care $care, \DateTimeImmutable $start, \DateTimeImmutable $end, int $meals): array
    {
        $user = $care->getUser();
        $nanny = $care->getNanny();
        if ($user === null || $nanny === null) {
            throw new \DomainException('Cette garde historique ne peut plus etre modifiee.');
        }

        $this->paymentCalculator->assertMonthEditable($user, $nanny, $care->getMonth());
        $oldMonths = [$care->getMonth() . '|' . $nanny->getPublicId() => $nanny];
        $segments = $this->segmenter->split($user, $nanny, $start, $end, $meals);
        $this->assertSegmentsCanBeSaved($user, $nanny, $segments, $care);

        $this->entityManager->remove($care);
        foreach ($segments as $segment) {
            $this->entityManager->persist($segment);
        }

        $this->entityManager->flush();
        $this->recalculateSegments($user, $segments, $oldMonths);
        $this->activityLogger->record($user, 'care.update', 'Garde modifiee.', [
            'nanny' => $nanny->getFullName(),
            'segments' => count($segments),
            'start' => $start->format(\DateTimeInterface::ATOM),
            'end' => $end->format(\DateTimeInterface::ATOM),
        ]);
        $this->entityManager->flush();

        return $segments;
    }

    public function delete(Care $care): void
    {
        $user = $care->getUser();
        $nanny = $care->getNanny();
        if ($user === null) {
            throw new \DomainException('Cette garde ne peut pas etre supprimee.');
        }
        if ($nanny === null) {
            throw new \DomainException('Cette garde historique ne peut plus etre supprimee.');
        }

        $this->paymentCalculator->assertMonthEditable($user, $nanny, $care->getMonth());
        $monthKey = $care->getMonth() . '|' . ($nanny?->getPublicId() ?? 'none');
        $this->entityManager->remove($care);
        $this->entityManager->flush();
        $this->paymentCalculator->recalculateMany($user, [$monthKey => $nanny]);
        $this->activityLogger->record($user, 'care.delete', 'Garde supprimee.', [
            'nanny' => $nanny->getFullName(),
            'date' => $care->getDate()?->format('Y-m-d'),
        ]);
        $this->entityManager->flush();
    }

    /** @param Care[] $segments */
    private function assertSegmentsCanBeSaved(User $user, Nanny $nanny, array $segments, ?Care $ignoredCare = null): void
    {
        foreach ($segments as $segment) {
            $this->paymentCalculator->assertMonthEditable($user, $nanny, $segment->getMonth());

            if ($this->careRepository->overlaps($user, $nanny, $segment->getStartDateTime(), $segment->getEndDateTime(), $ignoredCare)) {
                throw new \DomainException('Une garde existe deja sur ce creneau pour cette nounou.');
            }

            if ($this->rateRepository->findApplicableRate($user, $nanny, $segment->getDate()) === null) {
                throw new \DomainException(sprintf(
                    'Aucun tarif n\'est configure pour %s a la date du %s.',
                    $nanny->getFullName(),
                    $segment->getDate()->format('d/m/Y')
                ));
            }
        }
    }

    /** @param Care[] $segments @param array<string, Nanny|null> $extraMonths */
    private function recalculateSegments(User $user, array $segments, array $extraMonths = []): void
    {
        $months = $extraMonths;
        foreach ($segments as $segment) {
            $nanny = $segment->getNanny();
            $months[$segment->getMonth() . '|' . ($nanny?->getPublicId() ?? 'none')] = $nanny;
        }

        $this->paymentCalculator->recalculateMany($user, $months);
    }
}
