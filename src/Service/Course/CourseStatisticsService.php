<?php

namespace App\Service\Course;

use App\Entity\User;
use App\Repository\CourseReceiptRepository;
use App\Service\BankEncryptionService;
use App\Service\BankStatisticsService;

/**
 * Petit resume Courses pour le tableau de bord principal (App\Service\HomeDashboardService) et la page
 * d'accueil du module. Agregation en PHP sur les tickets confirmes de la periode courante, meme approche
 * que App\Service\BankStatisticsService::dashboard().
 *
 * La periode suit la "periode de vie" de Bank Manager (du dernier salaire jusqu'a la veille du prochain,
 * App\Service\BankStatisticsService::previousSalaryDate()/nextSalaryDate()) plutot que le mois civil,
 * car c'est la periode de fonctionnement reelle du foyer. Repli sur le mois civil si aucun repere de
 * salaire n'est configure.
 */
final class CourseStatisticsService
{
    public function __construct(
        private readonly CourseReceiptRepository $receiptRepository,
        private readonly BankEncryptionService $encryption,
        private readonly BankStatisticsService $bankStatistics,
    ) {
    }

    public function dashboard(User $user): array
    {
        $today = new \DateTimeImmutable('today');
        [$periodStart, $periodEnd, $periodLabel] = $this->currentPeriod($user, $today);

        $periodReceipts = $this->receiptRepository->findConfirmedForUserPeriod($user, $periodStart, $periodEnd);

        $periodSpend = 0.0;
        $byStore = [];
        foreach ($periodReceipts as $receipt) {
            $periodSpend += $receipt->getTotalAmount();
            $store = $receipt->getStore();
            $storeName = $store !== null ? $this->encryption->decrypt($store->getEncryptedName()) : 'Magasin inconnu';
            $byStore[$storeName] = ($byStore[$storeName] ?? 0.0) + $receipt->getTotalAmount();
        }
        arsort($byStore);

        $pendingReceipts = $this->receiptRepository->findPendingReviewForUser($user);

        return [
            'periodSpend' => round($periodSpend, 2),
            'periodReceiptCount' => count($periodReceipts),
            'periodLabel' => $periodLabel,
            'pendingReviewCount' => count($pendingReceipts),
            'storeBreakdown' => array_map(static fn ($name, $amount) => ['store' => $name, 'amount' => round($amount, 2)], array_keys($byStore), array_values($byStore)),
            'recentReceipts' => array_slice($this->receiptRepository->findConfirmedForUser($user, 5), 0, 5),
        ];
    }

    /** @return array{0:\DateTimeImmutable, 1:\DateTimeImmutable, 2:string} */
    private function currentPeriod(User $user, \DateTimeImmutable $today): array
    {
        $previousSalary = $this->bankStatistics->previousSalaryDate($user, $today);
        $nextSalary = $this->bankStatistics->nextSalaryDate($user, $today);

        if ($previousSalary !== null && $nextSalary !== null) {
            $periodEnd = $nextSalary->modify('-1 day');

            return [$previousSalary, $periodEnd, sprintf('Periode du %s au %s', $previousSalary->format('d/m'), $periodEnd->format('d/m'))];
        }

        $monthStart = $today->modify('first day of this month');
        $monthEnd = $today->modify('last day of this month');

        return [$monthStart, $monthEnd, 'Ce mois-ci'];
    }
}
