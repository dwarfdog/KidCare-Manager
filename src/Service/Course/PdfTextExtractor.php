<?php

namespace App\Service\Course;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Gere les deux cas de PDF : texte natif (facture numerique, extrait directement sans OCR) et
 * PDF image (ticket scanne/photographie) qui est rasterise page par page via Imagick puis passe
 * a l'OCR local (ImageTextExtractor). Imagick + Ghostscript sont requis uniquement pour ce second cas.
 */
final class PdfTextExtractor implements CourseTextExtractorInterface
{
    private const MIN_NATIVE_TEXT_LENGTH = 20;
    private const RASTER_RESOLUTION = 300;

    public function __construct(private readonly ImageTextExtractor $imageExtractor)
    {
    }

    public function supports(string $mimeType, string $extension): bool
    {
        return $mimeType === 'application/pdf' || strtolower($extension) === 'pdf';
    }

    public function extract(string $filePath): string
    {
        $nativeText = $this->extractNativeText($filePath);
        if (mb_strlen(trim($nativeText)) >= self::MIN_NATIVE_TEXT_LENGTH) {
            return $nativeText;
        }

        return $this->extractViaOcr($filePath);
    }

    private function extractNativeText(string $filePath): string
    {
        if (!class_exists(PdfParser::class)) {
            return '';
        }

        try {
            $document = (new PdfParser())->parseFile($filePath);

            return $document->getText();
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractViaOcr(string $filePath): string
    {
        if (!class_exists(\Imagick::class)) {
            throw new \RuntimeException(
                "Ce PDF ne contient pas de texte extractible directement (probablement un ticket scanne) et " .
                "l'extension PHP Imagick (+ Ghostscript) n'est pas installee sur ce serveur pour le convertir " .
                "en image avant OCR. Installez ext-imagick et Ghostscript, ou reexportez le ticket en photo/JPEG."
            );
        }

        $imagick = new \Imagick();
        try {
            $imagick->setResolution(self::RASTER_RESOLUTION, self::RASTER_RESOLUTION);
            $imagick->readImage($filePath);
            $imagick->setImageFormat('png');

            $pages = [];
            foreach ($imagick as $index => $page) {
                $pagePath = tempnam(sys_get_temp_dir(), 'course_pdf_page_') . '.png';
                $page->writeImage($pagePath);
                $pages[] = $pagePath;
            }

            $text = [];
            foreach ($pages as $pagePath) {
                try {
                    $text[] = $this->imageExtractor->extract($pagePath);
                } finally {
                    @unlink($pagePath);
                }
            }

            return implode("\n", $text);
        } finally {
            $imagick->clear();
        }
    }
}
