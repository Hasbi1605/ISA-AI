<?php

namespace App\Services\OnlyOffice;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Throwable;

class DocxTextExtractor
{
    /**
     * Extract plain text from a DOCX file.
     *
     * Returns an empty string if the file cannot be read or parsed,
     * so callers can treat extraction failure as a soft error.
     */
    public function extract(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            return '';
        }

        try {
            $phpWord = IOFactory::load($absolutePath);

            return $this->extractFromPhpWord($phpWord);
        } catch (Throwable) {
            return '';
        }
    }

    protected function extractFromPhpWord(PhpWord $phpWord): string
    {
        $lines = [];

        foreach ($phpWord->getSections() as $section) {
            $this->collectLines($section, $lines);
        }

        $text = implode("\n", array_filter(
            array_map('trim', $lines),
            fn (string $line) => $line !== '',
        ));

        return trim($text);
    }

    /**
     * @param  array<int, string>  $lines
     */
    protected function collectLines(AbstractContainer $container, array &$lines): void
    {
        foreach ($container->getElements() as $element) {
            if ($element instanceof Text) {
                $lines[] = (string) $element->getText();
                continue;
            }

            if ($element instanceof TextRun) {
                $parts = [];
                foreach ($element->getElements() as $child) {
                    if ($child instanceof Text) {
                        $parts[] = (string) $child->getText();
                    }
                }
                if ($parts !== []) {
                    $lines[] = implode('', $parts);
                }
                continue;
            }

            if ($element instanceof Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $this->collectLines($cell, $lines);
                    }
                }
                continue;
            }

            if ($element instanceof AbstractContainer) {
                $this->collectLines($element, $lines);
            }
        }
    }
}
