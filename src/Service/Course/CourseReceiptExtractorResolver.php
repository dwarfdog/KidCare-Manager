<?php

namespace App\Service\Course;

use App\Entity\CourseReceipt;

final class CourseReceiptExtractorResolver
{
    /** @var CourseTextExtractorInterface[] */
    private readonly array $extractors;

    public function __construct(
        ImageTextExtractor $imageExtractor,
        PdfTextExtractor $pdfExtractor,
        DocxTextExtractor $docxExtractor,
        PlainTextExtractor $plainTextExtractor,
    ) {
        $this->extractors = [$imageExtractor, $pdfExtractor, $docxExtractor, $plainTextExtractor];
    }

    public function resolve(string $mimeType, string $extension): ?CourseTextExtractorInterface
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mimeType, $extension)) {
                return $extractor;
            }
        }

        return null;
    }

    public function sourceFormatFor(string $mimeType, string $extension): string
    {
        $extension = strtolower($extension);

        return match (true) {
            $mimeType === 'application/pdf' || $extension === 'pdf' => CourseReceipt::SOURCE_PDF,
            $mimeType === 'text/plain' || $extension === 'txt' => CourseReceipt::SOURCE_TXT,
            $extension === 'docx' => CourseReceipt::SOURCE_DOCX,
            $extension === 'csv' => CourseReceipt::SOURCE_CSV,
            in_array($extension, ['xlsx', 'xls'], true) => CourseReceipt::SOURCE_XLSX,
            default => CourseReceipt::SOURCE_PHOTO,
        };
    }
}
