<?php

namespace App\Service\Course;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;

final class DocxTextExtractor implements CourseTextExtractorInterface
{
    public function supports(string $mimeType, string $extension): bool
    {
        return $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            || strtolower($extension) === 'docx';
    }

    public function extract(string $filePath): string
    {
        $phpWord = IOFactory::load($filePath, 'Word2007');
        $lines = [];
        foreach ($phpWord->getSections() as $section) {
            $this->collectText($section, $lines);
        }

        return implode("\n", array_filter($lines, static fn (string $l) => trim($l) !== ''));
    }

    /** @param string[] $lines */
    private function collectText(AbstractContainer $container, array &$lines): void
    {
        foreach ($container->getElements() as $element) {
            if ($element instanceof Text) {
                $lines[] = $element->getText();
            } elseif ($element instanceof TextRun) {
                $line = '';
                foreach ($element->getElements() as $child) {
                    if ($child instanceof Text) {
                        $line .= $child->getText();
                    }
                }
                $lines[] = $line;
            } elseif ($element instanceof Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $this->collectText($cell, $lines);
                    }
                }
            } elseif ($element instanceof AbstractContainer) {
                $this->collectText($element, $lines);
            }
        }
    }
}
