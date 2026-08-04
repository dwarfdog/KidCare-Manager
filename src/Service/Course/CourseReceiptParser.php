<?php

namespace App\Service\Course;

use App\Entity\CourseProduct;
use App\Entity\CourseReceiptLine;
use App\Model\CourseParsedLine;
use App\Model\CourseParsedReceipt;

/**
 * Heuristiques regex sur le texte brut d'un ticket (francais). Aucune IA : le meilleur effort est
 * complete par l'ecran de relecture systematique cote utilisateur (App\Service\Course\CourseReceiptImportService).
 * Entierement testable sans le binaire Tesseract puisqu'il opere sur du texte deja extrait.
 *
 * Calibre sur un ticket Systeme U (OmniPOS) reel : les lignes produit peuvent s'etaler sur 1, 2 ou 3
 * lignes de texte brut selon le format de caisse :
 *  - 1 ligne  : "TOMATES GRAPPE 0,450 KG X 2,99 EUR/KG 1,35"
 *  - 2 lignes : "GELATINE VAHINE ST 9 FEUILLES 1,36 € 11" puis "1 x 1,36 EUR" (confirmation de quantite,
 *               le "11" en fin de 1ere ligne est un code de taux de TVA, pas une quantite)
 *  - 3 lignes : "TRAVERS DE PORC TEX MEX 7,72 € 11" puis "Pesée manuelle" puis "0,596 kg x 12,95 €/kg"
 * La detection des lignes produit s'arrete au premier marqueur de fin de panier (TOTAL/SOUS-TOTAL/...)
 * pour ignorer tout le pied de ticket (detail TVA, carte de fidelite, moyen de paiement).
 */
final class CourseReceiptParser
{
    private const EXCLUDE_KEYWORDS = [
        'tva', 'especes', 'espèces', 'carte bancaire', 'carte bleue', 'cb ', 'rendu', 'monnaie',
        'merci', 'caisse', 'siret', 'siren', 'tel:', 'tel.', 'www.', 'ouvert', 'horaires', 'bienvenue',
        'fidelite', 'fidélité', 'remise', 'reduction', 'réduction', '*** vente ***',
    ];

    /** Premiere occurrence trouvee = fin du panier, tout ce qui suit est ignore pour les lignes produit. */
    private const CUTOFF_MARKERS = [
        'nombre de lignes', 'nb article', 'nombre article',
        'net a payer', 'net à payer', 'a payer', 'à payer', 'montant du', 'montant dû',
        'sous-total', 'sous total', 'total ttc', 'total',
    ];

    private const TOTAL_KEYWORDS = [
        'net a payer' => 100, 'net à payer' => 100,
        'a payer' => 90, 'à payer' => 90,
        'total ttc' => 80,
        'montant du' => 75, 'montant dû' => 75,
        'total' => 60,
    ];

    private const WEIGHING_MARKERS = ['pesee manuelle', 'pesée manuelle', 'pesee sur balance', 'pesée sur balance', 'poids'];

    public function parse(string $rawText): CourseParsedReceipt
    {
        $lines = $this->splitLines($rawText);
        $cutoff = $this->findProductsCutoffIndex($lines);
        $productLines = array_slice($lines, 0, $cutoff);

        return new CourseParsedReceipt(
            $this->detectStoreNameGuess($lines),
            $this->detectDate($lines),
            $this->detectTotal($lines),
            $this->detectProductLines($productLines),
        );
    }

    /** @return string[] */
    private function splitLines(string $rawText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
        $lines = array_map(static fn (string $l) => trim(preg_replace('/\s+/', ' ', $l) ?? ''), $lines);

        return array_values(array_filter($lines, static fn (string $l) => $l !== ''));
    }

    /**
     * Index de la premiere ligne marquant la fin du panier (TOTAL, SOUS-TOTAL, "Nombre de lignes
     * d'article"...). Tout ce qui suit (detail TVA, carte de fidelite, moyen de paiement, horaires...)
     * n'est jamais analyse comme ligne produit.
     */
    private function findProductsCutoffIndex(array $lines): int
    {
        foreach ($lines as $index => $line) {
            $lower = mb_strtolower($line);
            foreach (self::CUTOFF_MARKERS as $marker) {
                if (str_contains($lower, $marker)) {
                    return $index;
                }
            }
        }

        return count($lines);
    }

    private function detectStoreNameGuess(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 5) as $line) {
            if (mb_strlen($line) >= 3 && !preg_match('/\d{2}[\/.\-]\d{2}[\/.\-]\d{2,4}/', $line) && !$this->looksLikePriceLine($line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Prefere une date accompagnee d'une heure (horodatage de la transaction en pied de ticket,
     * ex: "27/07/26 17:58:43") a une simple date isolee, qui est souvent une plage promotionnelle
     * ("Du 06-07-2026 au 02-08-2026") plutot que la date d'achat reelle.
     */
    private function detectDate(array $lines): ?\DateTimeImmutable
    {
        $withTime = null;
        $any = null;
        foreach ($lines as $line) {
            if (!preg_match('/\b(?<day>\d{1,2})[\/.\-](?<month>\d{1,2})[\/.\-](?<year>\d{2,4})\b(?:\s+(?<hour>\d{1,2}):(?<minute>\d{2})(?::\d{2})?)?/', $line, $m)) {
                continue;
            }
            $date = $this->buildDate((int) $m['day'], (int) $m['month'], (int) $m['year']);
            if ($date === null) {
                continue;
            }
            $any ??= $date;
            if ($withTime === null && isset($m['hour']) && $m['hour'] !== '') {
                $withTime = $date;
            }
        }

        return $withTime ?? $any;
    }

    private function buildDate(int $day, int $month, int $year): ?\DateTimeImmutable
    {
        if ($year < 100) {
            $year += $year < 70 ? 2000 : 1900;
        }
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $day));

        return $date instanceof \DateTimeImmutable && $date <= new \DateTimeImmutable('+1 day') ? $date : null;
    }

    private function detectTotal(array $lines): ?float
    {
        $best = null;
        $bestScore = -1;
        foreach ($lines as $line) {
            $lower = mb_strtolower($line);
            foreach (self::TOTAL_KEYWORDS as $keyword => $score) {
                if (str_contains($lower, $keyword) && $score > $bestScore && preg_match('/(\d+[.,]\d{2})\s*(?:€|eur)?\s*$/i', $line, $m)) {
                    $best = $this->toFloat($m[1]);
                    $bestScore = $score;
                }
            }
        }

        return $best;
    }

    /**
     * Parcourt les lignes du panier avec anticipation (lookahead) pour recomposer les produits
     * etales sur 2 ou 3 lignes de texte avant de retomber sur les motifs mono-ligne.
     *
     * @param string[] $lines
     * @return CourseParsedLine[]
     */
    private function detectProductLines(array $lines): array
    {
        $result = [];
        $count = count($lines);
        $i = 0;
        while ($i < $count) {
            $line = $lines[$i];
            if ($this->isExcluded($line)) {
                ++$i;
                continue;
            }

            $lead = $this->matchLabelPrice($line);

            // Format 3 lignes : libelle+prix, marqueur de pesee, "qte UNITE x prix/UNITE".
            if ($lead !== null && $i + 2 < $count && $this->isWeighingMarker($lines[$i + 1])) {
                $weight = $this->matchWeightDetail($lines[$i + 2]);
                if ($weight !== null) {
                    $result[] = $this->buildLine($lead['label'], $weight['quantity'], $weight['unit'], $weight['unitPrice'], $lead['price']);
                    $i += 3;
                    continue;
                }
            }

            // Format 2 lignes : libelle+prix, puis "qte x prix EUR" (confirmation de quantite).
            if ($lead !== null && $i + 1 < $count) {
                $confirm = $this->matchConfirmation($lines[$i + 1]);
                if ($confirm !== null) {
                    $result[] = $this->buildLine($lead['label'], $confirm['quantity'], CourseProduct::UNIT_PIECE, $confirm['unitPrice'], $lead['price']);
                    $i += 2;
                    continue;
                }
            }

            // Format 1 ligne : tout est present sur la meme ligne (autre enseignes).
            if (($parsed = $this->matchWeightedLine($line)) !== null) {
                $result[] = $parsed;
                ++$i;
                continue;
            }
            if (($parsed = $this->matchQuantityLine($line)) !== null) {
                $result[] = $parsed;
                ++$i;
                continue;
            }

            // Repli : libelle + prix seuls, sans confirmation de quantite/poids (1 piece supposee).
            if ($lead !== null) {
                $result[] = new CourseParsedLine($lead['label'], 1.0, CourseProduct::UNIT_PIECE, $lead['price'], $lead['price'], CourseReceiptLine::CONFIDENCE_LOW);
                ++$i;
                continue;
            }

            ++$i;
        }

        return $result;
    }

    private function buildLine(string $label, float $quantity, string $unit, float $unitPrice, float $lineTotal): CourseParsedLine
    {
        $computed = round($quantity * $unitPrice, 2);
        $confidence = abs($lineTotal - $computed) < 0.02 ? CourseReceiptLine::CONFIDENCE_HIGH : CourseReceiptLine::CONFIDENCE_MEDIUM;

        return new CourseParsedLine($label, $quantity, $unit, $unitPrice, $lineTotal, $confidence);
    }

    private function isExcluded(string $line): bool
    {
        $lower = mb_strtolower($line);
        foreach (self::EXCLUDE_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return preg_match('/^\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}/', $line) === 1;
    }

    /**
     * Libelle + montant, avec un eventuel code de taux de TVA (1 a 3 chiffres) accole en fin de ligne
     * ("GELATINE VAHINE ST 9 FEUILLES 1,36 € 11" -> label="GELATINE VAHINE ST 9 FEUILLES", price=1.36).
     *
     * @return array{label:string, price:float}|null
     */
    private function matchLabelPrice(string $line): ?array
    {
        if (!preg_match('/^(?<label>.{2,80}?)\s+(?<price>\d+[.,]\d{2})\s*(?:€|EUR)?\s*(?:\d{1,3})?\s*$/iu', $line, $m)) {
            return null;
        }

        $label = trim($m['label']);
        if ($label === '' || preg_match('/^\d+$/', $label)) {
            return null;
        }

        return ['label' => $label, 'price' => $this->toFloat($m['price'])];
    }

    private function isWeighingMarker(string $line): bool
    {
        $lower = mb_strtolower($line);
        foreach (self::WEIGHING_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ligne de detail poids seule, sans libelle ni montant total (le total vient de la ligne precedente) :
     * "0,596 kg x 12,95 €/kg".
     *
     * @return array{quantity:float, unit:string, unitPrice:float}|null
     */
    private function matchWeightDetail(string $line): ?array
    {
        if (!preg_match('/^(?<qty>\d+[.,]\d{1,3})\s*(?<unit>KG|G|L|ML)\s*X\s*(?<price>\d+[.,]\d{2,4})\s*(?:€|EUR)?\s*(?:\/\s*(?:KG|G|L|ML))?\s*$/iu', $line, $m)) {
            return null;
        }

        return ['quantity' => $this->toFloat($m['qty']), 'unit' => $this->normalizeUnit($m['unit']), 'unitPrice' => $this->toFloat($m['price'])];
    }

    /**
     * Ligne de confirmation de quantite seule, sans libelle ni total ("1 x 1,36 EUR", "2 x 0,89").
     *
     * @return array{quantity:float, unitPrice:float}|null
     */
    private function matchConfirmation(string $line): ?array
    {
        if (!preg_match('/^(?<qty>\d{1,3})\s*X\s*(?<price>\d+[.,]\d{2,4})\s*(?:€|EUR)?\s*$/iu', $line, $m)) {
            return null;
        }

        return ['quantity' => (float) $m['qty'], 'unitPrice' => $this->toFloat($m['price'])];
    }

    /**
     * Ex: "TOMATES GRAPPE 0,450 KG X 2,99 EUR/KG 1,35" (tout sur une seule ligne, autres enseignes)
     */
    private function matchWeightedLine(string $line): ?CourseParsedLine
    {
        if (!preg_match(
            '/^(?<label>.{2,80}?)\s+(?<qty>\d+[.,]\d{1,3})\s*(?<unit>KG|G|L|ML)\s*X\s*(?<price>\d+[.,]\d{2,4})\s*(?:€|EUR)?(?:\/(?:KG|L))?\s*(?:=\s*)?(?<total>\d+[.,]\d{2})?\s*$/iu',
            $line,
            $m
        )) {
            return null;
        }

        $quantity = $this->toFloat($m['qty']);
        $unitPrice = $this->toFloat($m['price']);
        $unit = $this->normalizeUnit($m['unit']);
        $computed = round($quantity * $unitPrice, 2);
        $total = isset($m['total']) && $m['total'] !== '' ? $this->toFloat($m['total']) : $computed;
        $confidence = abs($total - $computed) < 0.02 ? CourseReceiptLine::CONFIDENCE_HIGH : CourseReceiptLine::CONFIDENCE_MEDIUM;

        return new CourseParsedLine(trim($m['label']), $quantity, $unit, $unitPrice, $total, $confidence);
    }

    /**
     * Ex: "YAOURT NATURE 2 X 0,89 1,78" ou "YAOURT NATURE 2X0,89"
     */
    private function matchQuantityLine(string $line): ?CourseParsedLine
    {
        if (!preg_match(
            '/^(?<label>.{2,80}?)\s+(?<qty>\d{1,3})\s*X\s*(?<price>\d+[.,]\d{2})\s*(?:=\s*)?(?<total>\d+[.,]\d{2})?\s*$/iu',
            $line,
            $m
        )) {
            return null;
        }

        $quantity = (float) $m['qty'];
        $unitPrice = $this->toFloat($m['price']);
        $computed = round($quantity * $unitPrice, 2);
        $total = isset($m['total']) && $m['total'] !== '' ? $this->toFloat($m['total']) : $computed;
        $confidence = abs($total - $computed) < 0.02 ? CourseReceiptLine::CONFIDENCE_HIGH : CourseReceiptLine::CONFIDENCE_MEDIUM;

        return new CourseParsedLine(trim($m['label']), $quantity, CourseProduct::UNIT_PIECE, $unitPrice, $total, $confidence);
    }

    private function looksLikePriceLine(string $line): bool
    {
        return (bool) preg_match('/\d+[.,]\d{2}\s*(?:€|EUR)?\s*$/i', $line);
    }

    private function normalizeUnit(string $unit): string
    {
        return match (strtoupper($unit)) {
            'KG' => CourseProduct::UNIT_KG,
            'G' => CourseProduct::UNIT_G,
            'L' => CourseProduct::UNIT_L,
            'ML' => CourseProduct::UNIT_ML,
            default => CourseProduct::UNIT_PIECE,
        };
    }

    private function toFloat(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
