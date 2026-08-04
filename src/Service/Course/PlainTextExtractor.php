<?php

namespace App\Service\Course;

final class PlainTextExtractor implements CourseTextExtractorInterface
{
    public function supports(string $mimeType, string $extension): bool
    {
        return $mimeType === 'text/plain' || strtolower($extension) === 'txt';
    }

    public function extract(string $filePath): string
    {
        return (string) file_get_contents($filePath);
    }
}
