<?php

namespace App\Service\Course;

use App\Entity\CourseProduct;
use App\Entity\CourseReceiptLine;
use App\Entity\User;
use App\Repository\CourseReceiptLineRepository;
use App\Service\BankEncryptionService;

/**
 * Evolution des prix dans le temps et comparatif entre magasins pour un produit. Pas de table de
 * resume dediee : agregation en PHP sur App\Entity\CourseReceiptLine deja charge (jointure ticket+magasin),
 * meme approche que App\Service\BankCategoryAnalyticsService::evolution(). Sortie prete pour Chart.js.
 */
final class CourseProductPriceAnalyticsService
{
    public function __construct(
        private readonly CourseReceiptLineRepository $lineRepository,
        private readonly BankEncryptionService $encryption,
    ) {
    }

    /**
     * Serie temporelle du prix unitaire (a la piece/au kg/au litre selon l'unite du produit) pour un
     * produit, tous magasins confondus, plus le detail par ligne pour un tableau.
     *
     * @return array{labels:string[], prices:array<int,?float>, rows:array<int,array{date:string,store:?string,unit:string,unitPrice:?float,quantity:float,lineTotal:float}>, hasData:bool}
     */
    public function evolution(User $user, CourseProduct $product): array
    {
        $lines = $this->lineRepository->findConfirmedForProduct($user, $product);

        $labels = [];
        $prices = [];
        $rows = [];
        foreach ($lines as $line) {
            $date = $line->getReceipt()->getPurchaseDate();
            $labels[] = $date?->format('d/m/Y') ?? '';
            $unitPrice = $line->getUnitPrice() ?? ($line->getQuantity() > 0 ? round($line->getLineTotal() / $line->getQuantity(), 4) : null);
            $prices[] = $unitPrice;
            $store = $line->getReceipt()->getStore();
            $rows[] = [
                'date' => $date?->format('d/m/Y') ?? '',
                'store' => $store !== null ? $this->encryption->decrypt($store->getEncryptedName()) : null,
                'unit' => $line->getUnit(),
                'unitPrice' => $unitPrice,
                'quantity' => $line->getQuantity(),
                'lineTotal' => $line->getLineTotal(),
            ];
        }

        return ['labels' => $labels, 'prices' => $prices, 'rows' => $rows, 'hasData' => $lines !== []];
    }

    /**
     * Dernier prix unitaire connu par magasin pour un produit (comparatif inter-magasins).
     *
     * @return array<string, array{store:\App\Entity\CourseStore, storeName:string, unitPrice:float, unit:string, date:\DateTimeInterface}>
     */
    public function latestPricesByStore(User $user, CourseProduct $product): array
    {
        $latest = [];
        foreach ($this->lineRepository->findConfirmedForProduct($user, $product) as $line) {
            $store = $line->getReceipt()->getStore();
            $date = $line->getReceipt()->getPurchaseDate();
            if ($store === null || $date === null) {
                continue;
            }
            $unitPrice = $line->getUnitPrice() ?? ($line->getQuantity() > 0 ? round($line->getLineTotal() / $line->getQuantity(), 4) : null);
            if ($unitPrice === null) {
                continue;
            }

            $key = $store->getPublicId();
            if (!isset($latest[$key]) || $date >= $latest[$key]['date']) {
                $latest[$key] = [
                    'store' => $store,
                    'storeName' => $this->encryption->decrypt($store->getEncryptedName()),
                    'unitPrice' => $unitPrice,
                    'unit' => $line->getUnit(),
                    'date' => $date,
                ];
            }
        }

        uasort($latest, static fn (array $a, array $b) => $a['unitPrice'] <=> $b['unitPrice']);

        return $latest;
    }
}
