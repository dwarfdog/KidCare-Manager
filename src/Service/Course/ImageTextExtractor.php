<?php

namespace App\Service\Course;

use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * OCR local via le binaire Tesseract (aucun appel externe). Le binaire et les paquets de langue
 * (ex: tesseract-ocr-fra) doivent etre installes sur la machine ; voir TESSERACT_BINARY_PATH / TESSERACT_LANGUAGES.
 */
final class ImageTextExtractor implements CourseTextExtractorInterface
{
    private const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/tiff'];
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff'];

    public function __construct(
        private readonly string $tesseractBinaryPath,
        private readonly string $tesseractLanguages,
    ) {
    }

    public function supports(string $mimeType, string $extension): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)
            || in_array(strtolower($extension), self::SUPPORTED_EXTENSIONS, true);
    }

    public function extract(string $filePath): string
    {
        if (!class_exists(TesseractOCR::class)) {
            throw new \RuntimeException('La librairie thiagoalessio/tesseract_ocr est manquante.');
        }

        try {
            $ocr = new TesseractOCR($filePath);
            $ocr->executable($this->tesseractBinaryPath !== '' ? $this->tesseractBinaryPath : 'tesseract');
            // PSM 6 (bloc de texte uniforme) : sans cela, Tesseract regroupe le texte par colonnes
            // (tous les libelles, puis tous les prix) des qu'un ticket a un grand espace entre le
            // libelle et le prix aligne a droite, ce qui casse le parsing ligne-par-ligne.
            // ->psm()/->lang() sont des methodes magiques (TesseractOCR::__call) sans annotation @method
            // fournie par la librairie ; call_user_func_array evite un faux positif d'analyse statique.
            call_user_func_array([$ocr, 'psm'], [6]);
            if ($this->tesseractLanguages !== '') {
                call_user_func_array([$ocr, 'lang'], explode('+', $this->tesseractLanguages));
            }

            return (string) $ocr->run();
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf(
                "Impossible d'executer l'OCR local (Tesseract). Verifiez que le binaire est installe et accessible " .
                "(TESSERACT_BINARY_PATH=%s). Erreur : %s",
                $this->tesseractBinaryPath,
                $e->getMessage()
            ), 0, $e);
        }
    }
}
