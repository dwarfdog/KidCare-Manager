<?php

namespace App\Service\Course;

use App\Entity\BankAccount;
use App\Entity\CourseImportProfile;
use App\Entity\CourseReceipt;
use App\Entity\CourseReceiptLine;
use App\Entity\User;
use App\Model\CourseParsedLine;
use App\Model\CourseParsedReceipt;
use App\Service\BankEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Orchestre l'import d'un ticket : extraction du texte/des lignes (App\Service\Course\CourseReceiptExtractorResolver,
 * App\Service\Course\CourseStructuredRowReader), analyse heuristique (App\Service\Course\CourseReceiptParser),
 * auto-association produit/magasin, puis creation d'un brouillon App\Entity\CourseReceipt en statut
 * "pending_review". Rien n'est jamais enregistre automatiquement sans passage par l'ecran de relecture
 * (App\Service\Course\CourseReceiptReviewService::confirm()).
 */
final class CourseReceiptImportService
{
    private const CSV_EXTENSIONS = ['csv'];
    private const XLSX_EXTENSIONS = ['xlsx', 'xls'];

    public function __construct(
        private readonly CourseReceiptExtractorResolver $extractorResolver,
        private readonly CourseReceiptParser $parser,
        private readonly CourseStructuredRowReader $structuredRowReader,
        private readonly CourseProductMatcher $productMatcher,
        private readonly CourseStoreMatcher $storeMatcher,
        private readonly CourseAttachmentManager $attachmentManager,
        private readonly CourseAuditLogger $auditLogger,
        private readonly BankEncryptionService $encryption,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function import(User $user, UploadedFile $file, ?BankAccount $account, ?CourseImportProfile $profile = null): CourseReceipt
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType() ?? '';

        $receipt = (new CourseReceipt())
            ->setUser($user)
            ->setAccount($account)
            ->setSourceFormat($this->extractorResolver->sourceFormatFor($mimeType, $extension))
            ->setStatus(CourseReceipt::STATUS_PROCESSING);

        try {
            $parsed = $this->extractParsedReceipt($receipt, $file->getPathname(), $mimeType, $extension, $profile);
            $this->applyParsedReceipt($user, $receipt, $parsed);
            $receipt->setStatus(CourseReceipt::STATUS_PENDING_REVIEW);
        } catch (\Throwable $e) {
            $receipt->setStatus(CourseReceipt::STATUS_FAILED);
            $receipt->setProcessingErrors([$e->getMessage()]);
        }

        $this->entityManager->persist($receipt);
        $this->attachmentManager->attach($user, $receipt, $file);
        $this->auditLogger->record($user, 'courses.receipt.import', 'course_receipt', $receipt->getPublicId(), [
            'source_format' => $receipt->getSourceFormat(),
            'status' => $receipt->getStatus(),
        ]);

        return $receipt;
    }

    private function extractParsedReceipt(CourseReceipt $receipt, string $filePath, string $mimeType, string $extension, ?CourseImportProfile $profile): CourseParsedReceipt
    {
        if (in_array($extension, self::CSV_EXTENSIONS, true) || in_array($extension, self::XLSX_EXTENSIONS, true)) {
            return $this->extractStructured($receipt, $filePath, $extension, $profile);
        }

        $extractor = $this->extractorResolver->resolve($mimeType, $extension);
        if ($extractor === null) {
            throw new \RuntimeException(sprintf("Format de fichier non reconnu (%s). Formats acceptes : photo, PDF, TXT, DOCX, CSV, XLSX.", $extension !== '' ? '.' . $extension : $mimeType));
        }

        $rawText = $extractor->extract($filePath);
        if (trim($rawText) === '') {
            throw new \RuntimeException("Aucun texte n'a pu etre extrait de ce fichier. Verifiez la qualite de la photo/du scan ou saisissez le ticket manuellement.");
        }

        $receipt->setEncryptedOcrRawText($this->encryption->encrypt($rawText));

        return $this->parser->parse($rawText);
    }

    private function extractStructured(CourseReceipt $receipt, string $filePath, string $extension, ?CourseImportProfile $profile): CourseParsedReceipt
    {
        if (in_array($extension, self::CSV_EXTENSIONS, true)) {
            $inspection = $this->structuredRowReader->inspectCsv((string) file_get_contents($filePath));
        } else {
            $inspection = $this->structuredRowReader->inspectXlsx($filePath);
        }

        if ($profile !== null) {
            $lines = $this->structuredRowReader->mapRowsToLines($inspection['rows'], $profile);
            $total = array_sum(array_map(static fn (CourseParsedLine $l) => $l->lineTotal, $lines));

            return new CourseParsedReceipt(null, null, $total > 0 ? $total : null, $lines);
        }

        // Pas de profil de mapping connu : on retombe sur le texte libre, analyse comme un ticket scanne.
        $plainText = $this->structuredRowReader->rowsToPlainText($inspection['headers'], $inspection['rows']);
        $receipt->setEncryptedOcrRawText($this->encryption->encrypt($plainText));

        return $this->parser->parse($plainText);
    }

    private function applyParsedReceipt(User $user, CourseReceipt $receipt, CourseParsedReceipt $parsed): void
    {
        $receipt->setPurchaseDate($parsed->date ?? new \DateTime('today'));
        $total = $parsed->total ?? array_sum(array_map(static fn (CourseParsedLine $l) => $l->lineTotal, $parsed->lines));
        $receipt->setTotalAmount($total);
        $receipt->setEncryptedStoreNameGuess($this->encryption->encrypt($parsed->storeNameGuess));
        $store = $this->storeMatcher->match($user, $parsed->storeNameGuess);
        $receipt->setStore($store);

        $catalog = $this->productMatcher->buildCatalog($user);
        foreach ($parsed->lines as $parsedLine) {
            $product = $this->productMatcher->match($user, $parsedLine->rawLabel, $catalog, $store);

            $line = (new CourseReceiptLine())
                ->setUser($user)
                ->setEncryptedRawLabel($this->encryption->encrypt($parsedLine->rawLabel) ?? '')
                ->setProduct($product)
                ->setCategory($product?->getCategory())
                ->setQuantity($parsedLine->quantity)
                ->setUnit($parsedLine->unit)
                ->setUnitPrice($parsedLine->unitPrice)
                ->setLineTotal($parsedLine->lineTotal)
                ->setConfidence($parsedLine->confidence);

            $receipt->addLine($line);
        }
    }
}
