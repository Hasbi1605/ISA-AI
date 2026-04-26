<?php

namespace App\Services\Document\Parsing;

use Smalot\PdfParser\Parser as PdfParserEngine;

class PdfParser implements DocumentParserInterface
{
    protected array $pages = [];

    public function parse(string $filePath): array
    {
        $this->pages = [];
        
        try {
            $parser = new PdfParserEngine();
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            
            foreach ($pages as $index => $page) {
                $text = $page->getText();
                $text = $this->cleanText($text);
                
                if (!empty(trim($text))) {
                    $this->pages[] = [
                        'page_content' => $text,
                        'page_number' => $index + 1,
                        'source' => 'pdf',
                    ];
                }
            }

            if (empty($this->pages)) {
                throw new \RuntimeException('PDF contains no extractable text (may be scanned/image-based)');
            }
            
        } catch (\Exception $e) {
            throw $e;
        }
        
        return $this->pages;
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/pdf',
            'application/x-pdf',
        ]);
    }

    protected function cleanText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
}