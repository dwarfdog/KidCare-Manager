<?php

namespace App\Service\Course;

interface CourseTextExtractorInterface
{
    public function supports(string $mimeType, string $extension): bool;

    /**
     * Extrait le texte brut d'un fichier (ticket/facture) pour analyse ulterieure par CourseReceiptParser.
     */
    public function extract(string $filePath): string;
}
