<?php

namespace App\Services\Documents;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html as SpreadsheetHtmlWriter;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Throwable;

class DocumentPreviewRenderer
{
    public const DISK = 'local';

    public const PREVIEW_DIR = 'document-previews';

    public function supports(Document $document): bool
    {
        return $this->isPdf($document) || $this->isDocx($document) || $this->isXlsx($document);
    }

    public function isPdf(Document $document): bool
    {
        return in_array($document->mime_type, Document::PDF_MIME_TYPES, true);
    }

    public function isDocx(Document $document): bool
    {
        return in_array($document->mime_type, Document::DOCX_MIME_TYPES, true);
    }

    public function isXlsx(Document $document): bool
    {
        return in_array($document->mime_type, Document::XLSX_MIME_TYPES, true);
    }

    /**
     * Render the document preview, persist the HTML to storage, and update the model.
     * For PDF documents this is a no-op because the viewer streams the PDF inline.
     */
    public function render(Document $document): void
    {
        if (! $this->supports($document)) {
            $this->markFailed($document, 'Unsupported MIME type for preview.');

            return;
        }

        if ($this->isPdf($document)) {
            $document->forceFill([
                'preview_html_path' => null,
                'preview_status' => Document::PREVIEW_STATUS_READY,
            ])->save();

            return;
        }

        try {
            $absolutePath = $this->resolveSourcePath($document);

            if ($absolutePath === null) {
                $this->markFailed($document, 'Source file not found in storage.');

                return;
            }

            $html = $this->isDocx($document)
                ? $this->renderDocx($absolutePath)
                : $this->renderXlsx($absolutePath);

            $previewPath = $this->buildPreviewPath($document);
            Storage::disk(self::DISK)->put($previewPath, $html);

            $document->forceFill([
                'preview_html_path' => $previewPath,
                'preview_status' => Document::PREVIEW_STATUS_READY,
            ])->save();
        } catch (Throwable $e) {
            $this->markFailed($document, $e->getMessage());
        }
    }

    public function deletePreview(Document $document): void
    {
        if ($document->preview_html_path && Storage::disk(self::DISK)->exists($document->preview_html_path)) {
            Storage::disk(self::DISK)->delete($document->preview_html_path);
        }
    }

    protected function renderDocx(string $absolutePath): string
    {
        $phpWord = WordIOFactory::load($absolutePath);
        $writer = WordIOFactory::createWriter($phpWord, 'HTML');

        ob_start();
        try {
            $writer->save('php://output');
        } finally {
            $raw = (string) ob_get_clean();
        }

        return $this->extractBody($raw);
    }

    protected function renderXlsx(string $absolutePath): string
    {
        $spreadsheet = SpreadsheetIOFactory::load($absolutePath);
        $writer = new SpreadsheetHtmlWriter($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->writeAllSheets();

        ob_start();
        try {
            $writer->save('php://output');
        } finally {
            $raw = (string) ob_get_clean();
        }

        return $this->extractBody($raw);
    }

    protected function extractBody(string $html): string
    {
        if (preg_match('#<body[^>]*>(.*?)</body>#is', $html, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim($html);
    }

    protected function resolveSourcePath(Document $document): ?string
    {
        $candidates = [
            $document->file_path,
            'private/'.$document->file_path,
        ];

        foreach ($candidates as $candidate) {
            if (! $candidate) {
                continue;
            }

            $absolute = Storage::disk(self::DISK)->path($candidate);
            if (is_file($absolute)) {
                return $absolute;
            }
        }

        return null;
    }

    protected function buildPreviewPath(Document $document): string
    {
        return self::PREVIEW_DIR.'/'.$document->user_id.'/'.$document->id.'.html';
    }

    protected function markFailed(Document $document, string $reason): void
    {
        logger()->warning('Document preview render failed', [
            'document_id' => $document->id,
            'reason' => $reason,
        ]);

        $document->forceFill([
            'preview_status' => Document::PREVIEW_STATUS_FAILED,
        ])->save();
    }
}
