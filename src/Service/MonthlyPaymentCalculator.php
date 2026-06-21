<?php

namespace App\Service;

use App\Entity\MonthlyPayment;
use App\Entity\Nanny;
use App\Entity\User;
use App\Repository\CareRepository;
use App\Repository\MonthlyPaymentRepository;
use App\Repository\NannyRateRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MonthlyPaymentCalculator
{
    public function __construct(
        private readonly CareRepository $careRepository,
        private readonly MonthlyPaymentRepository $paymentRepository,
        private readonly NannyRateRepository $rateRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function assertMonthEditable(User $user, ?Nanny $nanny, string $month): void
    {
        $payment = $this->paymentRepository->findOneFor($user, $nanny, $month);
        if ($payment?->isPaid()) {
            throw new \DomainException('Ce mois est deja marque comme paye. Les modifications sont bloquees.');
        }
    }

    public function recalculate(User $user, ?Nanny $nanny, string $month, bool $force = false, ?string $nannySnapshotKey = null): ?MonthlyPayment
    {
        $payment = $this->paymentRepository->findOneFor($user, $nanny, $month, $nannySnapshotKey);
        if ($payment?->isPaid() && !$force) {
            return $payment;
        }

        $snapshotKey = $payment?->getNannySnapshotKey() ?? $nannySnapshotKey ?? $nanny?->getPublicId();
        $cares = $this->careRepository->findForPayment($user, $nanny, $month, $snapshotKey);
        if ($cares === []) {
            if ($payment !== null && !$payment->isPaid()) {
                $this->entityManager->remove($payment);
            }

            return null;
        }

        if ($payment === null) {
            $payment = new MonthlyPayment();
            $payment->setUser($user);
            $payment->setNanny($nanny);
            $payment->setNannyDisplayName($nanny?->getFullName() ?? $cares[0]->getNannyDisplayName());
            $payment->setNannySnapshotKey($nanny?->getPublicId() ?? $cares[0]->getNannySnapshotKey());
            $payment->setMonth($month);
            $this->entityManager->persist($payment);
        }

        $hours = 0.0;
        $meals = 0;
        $amountHours = 0.0;
        $amountMeals = 0.0;

        foreach ($cares as $care) {
            $hours += $care->getHoursCount();
            $meals += $care->getMealsCount();

            $rate = $nanny !== null
                ? $this->rateRepository->findApplicableRate($user, $nanny, $care->getDate())
                : ($care->getNannySnapshotKey() !== null
                    ? $this->rateRepository->findApplicableRateForSnapshot($user, $care->getNannySnapshotKey(), $care->getDate())
                    : null);

            if ($rate === null) {
                throw new \DomainException(sprintf(
                    'Aucun tarif applicable pour %s a la date du %s.',
                    $nanny?->getFullName() ?? $care->getNannyDisplayName(),
                    $care->getDate()->format('d/m/Y')
                ));
            }

            $amountHours += $care->getHoursCount() * $rate->getHourlyRate();
            $amountMeals += $care->getMealsCount() * $rate->getMealRate();
        }

        $payment
            ->setTotalsHours($hours)
            ->setTotalMeals($meals)
            ->setAmountHours($amountHours)
            ->setAmountMeals($amountMeals)
            ->setTotalAmount($amountHours + $amountMeals)
            ->setNannyDisplayName($nanny?->getFullName() ?? $payment->getNannyDisplayName())
            ->setNannySnapshotKey($nanny?->getPublicId() ?? $payment->getNannySnapshotKey());

        return $payment;
    }

    /** @param array<string, Nanny|null> $months */
    public function recalculateMany(User $user, array $months): void
    {
        foreach ($months as $key => $nanny) {
            [$month] = explode('|', $key, 2);
            $this->recalculate($user, $nanny, $month);
        }
    }
}
