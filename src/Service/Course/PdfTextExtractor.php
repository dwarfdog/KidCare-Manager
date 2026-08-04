<?php

namespace App\Service\Course;

use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\Process\Process;

/**
 * Gere les deux cas de PDF : texte natif (facture numerique, extrait directement sans OCR) et
 * PDF image (ticket scanne/photographie) qui est rasterise page par page en appelant directement le
 * binaire Ghostscript (voir GHOSTSCRIPT_BINARY_PATH), puis passe a l'OCR local (ImageTextExtractor).
 * Ghostscript seul est requis pour ce second cas ; l'extension PHP Imagick n'est plus utilisee ici
 * (son coder PDF s'est avere non fiable sur certains builds Windows).
 */
final class PdfTextExtractor implements CourseTextExtractorInterface
{
    private const MIN_NATIVE_TEXT_LENGTH = 20;
    private const RASTER_RESOLUTION = 300;

    public function __construct(
        private readonly ImageTextExtractor $imageExtractor,
        private readonly string $ghostscriptBinaryPath,
    ) {
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
        if (trim($this->ghostscriptBinaryPath) === '') {
            throw new \RuntimeException(
                "Ce PDF ne contient pas de texte extractible directement (probablement un ticket scanne) et " .
                "Ghostscript n'est pas configure sur ce serveur pour le convertir en image avant OCR. " .
                "Installez Ghostscript et renseignez GHOSTSCRIPT_BINARY_PATH, ou reexportez le ticket en photo/JPEG."
            );
        }

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'course_pdf_' . bin2hex(random_bytes(8));
        if (!mkdir($tempDir) && !is_dir($tempDir)) {
            throw new \RuntimeException("Impossible de creer un dossier temporaire pour convertir ce PDF.");
        }

        try {
            $outputPattern = $tempDir . DIRECTORY_SEPARATOR . 'page_%03d.png';
            $process = new Process([
                $this->ghostscriptBinaryPath,
                '-q',
                '-dQUIET',
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-dNOPROMPT',
                '-sDEVICE=png16m',
                '-r' . self::RASTER_RESOLUTION,
                '-sOutputFile=' . $outputPattern,
                $filePath,
            ]);
            // Ghostscript a besoin de TEMP/TMP pour son propre fichier de travail interne ; certains
            // contextes serveur (ex: process Apache lance manuellement, sans session utilisateur normale)
            // n'ont pas ces variables definies, ce qui fait echouer Ghostscript des le demarrage. On les
            // force explicitement plutot que de dependre de l'environnement du process parent.
            $process->run(null, ['TEMP' => sys_get_temp_dir(), 'TMP' => sys_get_temp_dir()]);

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(sprintf(
                    "Ce PDF ne contient pas de texte extractible directement (probablement un ticket scanne) et " .
                    "la conversion en image via Ghostscript a echoue sur ce serveur. Verifiez l'installation de " .
                    "Ghostscript (GHOSTSCRIPT_BINARY_PATH=%s), ou reexportez le ticket en photo/JPEG. Erreur : %s",
                    $this->ghostscriptBinaryPath,
                    trim($process->getErrorOutput()) ?: trim($process->getOutput())
                ));
            }

            $pages = glob($tempDir . DIRECTORY_SEPARATOR . 'page_*.png') ?: [];
            sort($pages);
            if ($pages === []) {
                throw new \RuntimeException("La conversion Ghostscript n'a produit aucune page image pour ce PDF.");
            }

            $text = [];
            foreach ($pages as $pagePath) {
                $text[] = $this->imageExtractor->extract($pagePath);
            }

            return implode("\n", $text);
        } finally {
            foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*.png') ?: [] as $leftover) {
                @unlink($leftover);
            }
            @rmdir($tempDir);
        }
    }
}
