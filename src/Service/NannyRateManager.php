<?php

namespace App\Service;

use App\Entity\Nanny;
use App\Entity\NannyRate;
use App\Entity\User;
use App\Repository\CareRepository;
use App\Repository\NannyRateRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NannyRateManager
{
    public function __construct(
        private readonly CareRepository $careRepository,
        private readonly NannyRateRepository $rateRepository,
        private readonly MonthlyPaymentCalculator $paymentCalculator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function add(User $user, Nanny $nanny, NannyRate $rate): void
    {
        $this->assertNoDuplicateDate($user, $nanny, $rate);
        $to = $this->windowEnd($user, $nanny, $rate->getEffectiveFrom(), $rate);
        $this->assertNoPaidImpact($user, $nanny, $rate->getEffectiveFrom(), $to);
        $months = $this->impactedMonths($user, $nanny, $rate->getEffectiveFrom(), $to);

        $this->entityManager->wrapInTransaction(function () use ($user, $nanny, $rate, $months): void {
            $this->entityManager->persist($rate);
            $this->entityManager->flush();
            $this->paymentCalculator->recalculateMany($user, $this->monthsForCalculator($months, $nanny));
            $this->activityLogger->record($user, 'nanny_rate.add', 'Tarif nounou ajoute.', [
                'nanny' => $nanny->getFullName(),
                'effectiveFrom' => $rate->getEffectiveFrom()->format('Y-m-d'),
            ]);
            $this->entityManager->flush();
        });
    }

    public function update(User $user, Nanny $nanny, NannyRate $rate, \DateTimeInterface $previousEffectiveFrom): void
    {
        $this->assertNoDuplicateDate($user, $nanny, $rate);
        $previousEnd = $this->windowEnd($user, $nanny, $previousEffectiveFrom, $rate);
        $newEnd = $this->windowEnd($user, $nanny, $rate->getEffectiveFrom(), $rate);
        $this->assertNoPaidImpact($user, $nanny, $previousEffectiveFrom, $previousEnd);
        $this->assertNoPaidImpact($user, $nanny, $rate->getEffectiveFrom(), $newEnd);

        $months = $this->impactedMonths($user, $nanny, $previousEffectiveFrom, $previousEnd)
            + $this->impactedMonths($user, $nanny, $rate->getEffectiveFrom(), $newEnd);

        $this->entityManager->wrapInTransaction(function () use ($user, $nanny, $rate, $months): void {
            $this->entityManager->flush();
            $this->paymentCalculator->recalculateMany($user, $this->monthsForCalculator($months, $nanny));
            $this->activityLogger->record($user, 'nanny_rate.update', 'Tarif nounou modifie.', [
                'nanny' => $nanny->getFullName(),
                'effectiveFrom' => $rate->getEffectiveFrom()->format('Y-m-d'),
            ]);
            $this->entityManager->flush();
        });
    }

    public function delete(User $user, Nanny $nanny, NannyRate $rate): void
    {
        $from = $rate->getEffectiveFrom();
        $to = $this->windowEnd($user, $nanny, $from, $rate);
        $this->assertNoPaidImpact($user, $nanny, $from, $to);
        $months = $this->impactedMonths($user, $nanny, $from, $to);

        $this->entityManager->wrapInTransaction(function () use ($user, $nanny, $rate, $months): void {
            $effectiveFrom = $rate->getEffectiveFrom()->format('Y-m-d');
            $rate->archive();
            $this->entityManager->flush();
            $this->paymentCalculator->recalculateMany($user, $this->monthsForCalculator($months, $nanny));
            $this->activityLogger->record($user, 'nanny_rate.delete', 'Tarif nounou supprime.', [
                'nanny' => $nanny->getFullName(),
                'effectiveFrom' => $effectiveFrom,
            ]);
            $this->entityManager->flush();
        });
    }

    private function assertNoDuplicateDate(User $user, Nanny $nanny, NannyRate $rate): void
    {
        if ($this->rateRepository->existsForEffectiveDate($user, $nanny, $rate->getEffectiveFrom(), $rate)) {
            throw new \DomainException('Un tarif existe deja a cette date pour cette nounou.');
        }
    }

    private function assertNoPaidImpact(User $user, Nanny $nanny, \DateTimeInterface $from, ?\DateTimeInterface $to): void
    {
        if ($this->careRepository->hasPaidCareInRange($user, $nanny, $from, $to)) {
            throw new \DomainException('Ce tarif touche un mois deja paye. Modification bloquee.');
        }
    }

    private function windowEnd(User $user, Nanny $nanny, \DateTimeInterface $from, ?NannyRate $excludedRate): ?\DateTimeInterface
    {
        return $this->rateRepository->findNextRate($user, $nanny, $from, $excludedRate)?->getEffectiveFrom();
    }

    /** @return array<string, string> */
    private function impactedMonths(User $user, Nanny $nanny, \DateTimeInterface $from, ?\DateTimeInterface $to): array
    {
        $months = [];
        foreach ($this->careRepository->findUnpaidMonthsInRange($user, $nanny, $from, $to) as $month) {
            $months[$month] = $month;
        }

        return $months;
    }

    /** @param array<string, string> $months @return array<string, Nanny> */
    private function monthsForCalculator(array $months, Nanny $nanny): array
    {
        $result = [];
        foreach ($months as $month) {
            $result[$month . '|' . $nanny->getPublicId()] = $nanny;
        }

        return $result;
    }
}
