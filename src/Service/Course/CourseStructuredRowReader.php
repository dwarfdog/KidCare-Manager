<?php

namespace App\Service\Course;

use App\Entity\CourseImportProfile;
use App\Entity\CourseProduct;
use App\Entity\CourseReceiptLine;
use App\Model\CourseParsedLine;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lecture des exports CSV/XLSX structures a colonnes fixes (ex: export de courses en ligne type Drive).
 * Reutilise league/csv (deja utilise par l'import bancaire) et phpoffice/phpspreadsheet (deja utilise
 * pour les exports Bank). Si aucun App\Entity\CourseImportProfile ne correspond, le texte concatene des
 * cellules est renvoye pour etre analyse par CourseReceiptParser comme un ticket en texte libre.
 */
final class CourseStructuredRowReader
{
    /** @return array{delimiter:string, headers:string[], rows:array<int,array<string,string>>} */
    public function inspectCsv(string $content): array
    {
        $first = strtok($content, "\r\n") ?: '';
        $delimiters = [';' => substr_count($first, ';'), ',' => substr_count($first, ','), "\t" => substr_count($first, "\t")];
        arsort($delimiters);
        $delimiter = (string) array_key_first($delimiters);

        $csv = Reader::createFromString($content);
        $csv->setDelimiter($delimiter);
        $csv->setHeaderOffset(0);

        return [
            'delimiter' => $delimiter,
            'headers' => $csv->getHeader(),
            'rows' => array_values(iterator_to_array($csv->getRecords())),
        ];
    }

    /** @return array{headers:string[], rows:array<int,array<string,string>>} */
    public function inspectXlsx(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);
        if ($data === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(static fn ($h) => trim((string) $h), array_shift($data));
        $rows = [];
        foreach ($data as $rawRow) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($rawRow[$index] ?? ''));
            }
            if (array_filter($row, static fn ($v) => $v !== '') !== []) {
                $rows[] = $row;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return CourseParsedLine[]
     */
    public function mapRowsToLines(array $rows, CourseImportProfile $profile): array
    {
        $mapping = $profile->getMapping();
        $lines = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row[$mapping['label'] ?? ''] ?? ''));
            if ($label === '') {
                continue;
            }
            $total = $this->toFloat((string) ($row[$mapping['total'] ?? ''] ?? '0'));
            $quantity = isset($mapping['quantity']) ? $this->toFloat((string) ($row[$mapping['quantity']] ?? '1')) : 1.0;
            $unit = isset($mapping['unit']) ? $this->normalizeUnit((string) ($row[$mapping['unit']] ?? '')) : CourseProduct::UNIT_PIECE;
            $unitPrice = isset($mapping['unitPrice']) && ($row[$mapping['unitPrice']] ?? '') !== ''
                ? $this->toFloat((string) $row[$mapping['unitPrice']])
                : ($quantity > 0 ? round($total / $quantity, 4) : null);

            $lines[] = new CourseParsedLine($label, $quantity ?: 1.0, $unit, $unitPrice, $total, CourseReceiptLine::CONFIDENCE_HIGH);
        }

        return $lines;
    }

    /**
     * Concatene les cellules en texte libre pour un fichier structure sans profil de mapping connu,
     * afin de reutiliser le meme parseur heuristique que les tickets scannes.
     *
     * @param string[] $headers
     * @param array<int,array<string,string>> $rows
     */
    public function rowsToPlainText(array $headers, array $rows): string
    {
        $lines = [implode(' ', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(' ', array_map('strval', $row));
        }

        return implode("\n", $lines);
    }

    private function normalizeUnit(string $unit): string
    {
        $unit = strtoupper(trim($unit));

        return in_array($unit, ['KG', 'G', 'L', 'ML'], true)
            ? match ($unit) { 'KG' => CourseProduct::UNIT_KG, 'G' => CourseProduct::UNIT_G, 'L' => CourseProduct::UNIT_L, default => CourseProduct::UNIT_ML }
            : CourseProduct::UNIT_PIECE;
    }

    private function toFloat(string $value): float
    {
        $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }

        return (float) str_replace(',', '.', $value);
    }
}
