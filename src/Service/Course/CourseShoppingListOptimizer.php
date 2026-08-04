<?php

namespace App\Service\Course;

use App\Entity\CourseShoppingList;
use App\Entity\User;
use App\Service\BankEncryptionService;

/**
 * Calcule les deux scenarios "ou faire les courses moins cher" a partir des derniers prix connus par
 * magasin (App\Service\Course\CourseProductPriceAnalyticsService::latestPricesByStore) : le magasin unique
 * le moins cher, et la repartition optimisee (chaque article vers son magasin le moins cher connu). Pas
 * d'optimisation d'itineraire geographique : la "repartition" est un regroupement par magasin, pas un
 * calcul de trajet.
 */
final class CourseShoppingListOptimizer
{
    public function __construct(
        private readonly CourseProductPriceAnalyticsService $priceAnalytics,
        private readonly BankEncryptionService $encryption,
    ) {
    }

    /**
     * @return array{stores: array<int, array{store:\App\Entity\CourseStore, storeName:string, total:float, coveredCount:int, missingCount:int, missingItems:string[]}>}
     */
    public function bestSingleStore(User $user, CourseShoppingList $list): array
    {
        $items = $list->getItems();
        $totalsByStore = [];

        foreach ($items as $item) {
            $prices = $this->priceAnalytics->latestPricesByStore($user, $item->getProduct());
            foreach ($prices as $storeKey => $entry) {
                $totalsByStore[$storeKey] ??= [
                    'store' => $entry['store'],
                    'storeName' => $entry['storeName'],
                    'total' => 0.0,
                    'coveredCount' => 0,
                    'missingItems' => [],
                ];
                $totalsByStore[$storeKey]['total'] += $entry['unitPrice'] * $item->getDesiredQuantity();
                ++$totalsByStore[$storeKey]['coveredCount'];
            }
        }

        $itemCount = count($items);
        foreach ($totalsByStore as $storeKey => $entry) {
            $totalsByStore[$storeKey]['missingCount'] = $itemCount - $entry['coveredCount'];
            $totalsByStore[$storeKey]['total'] = round($entry['total'], 2);
        }

        uasort($totalsByStore, static function (array $a, array $b) {
            return $b['coveredCount'] <=> $a['coveredCount'] ?: $a['total'] <=> $b['total'];
        });

        return ['stores' => array_values($totalsByStore)];
    }

    /**
     * @return array{groups: array<int, array{store:?\App\Entity\CourseStore, storeName:string, items:array<int,array{label:string,unitPrice:float,quantity:float,lineTotal:float}>, subtotal:float}>, grandTotal:float, unknownItems:string[]}
     */
    public function multiStoreSplit(User $user, CourseShoppingList $list): array
    {
        $groups = [];
        $unknownItems = [];
        $grandTotal = 0.0;

        foreach ($list->getItems() as $item) {
            $prices = $this->priceAnalytics->latestPricesByStore($user, $item->getProduct());
            if ($prices === []) {
                $unknownItems[] = $this->encryption->decrypt($item->getProduct()->getEncryptedName());
                continue;
            }

            $best = array_shift($prices);
            $key = $best['store']->getPublicId();
            $groups[$key] ??= ['store' => $best['store'], 'storeName' => $best['storeName'], 'items' => [], 'subtotal' => 0.0];
            $lineTotal = round($best['unitPrice'] * $item->getDesiredQuantity(), 2);
            $groups[$key]['items'][] = [
                'unitPrice' => $best['unitPrice'],
                'quantity' => $item->getDesiredQuantity(),
                'unit' => $item->getUnit(),
                'lineTotal' => $lineTotal,
            ];
            $groups[$key]['subtotal'] = round($groups[$key]['subtotal'] + $lineTotal, 2);
            $grandTotal += $lineTotal;
        }

        return ['groups' => array_values($groups), 'grandTotal' => round($grandTotal, 2), 'unknownItems' => $unknownItems];
    }
}
