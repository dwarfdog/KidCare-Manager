<?php

namespace App\Service;

/**
 * Service pour gérer les arrondis des valeurs numériques.
 */
class RoundingService
{
    /**
     * Arrondit une valeur numérique à 2 décimales.
     *
     * @param float|int $value La valeur à arrondir.
     * @return float La valeur arrondie à 2 décimales.
     *
     * @throws \InvalidArgumentException Si la valeur n'est pas numérique.
     */
    public function roundToTwoDecimals($value): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('La valeur fournie doit être numérique.');
        }

        return round((float)$value, 2);
    }
}
