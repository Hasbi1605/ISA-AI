<?php

namespace App\Services;

use App\Models\Document;
use App\Services\Documents\DocumentPreviewRenderer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentExportService
{
    protected string $baseUrl;
    protected ?string $token;
    protected int $connectTimeout;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            $this->normalizeStringConfig(config('services.ai_document_service.url'), 'http://127.0.0.1:8001'),
            '/'
        );
        $this->token = $this->normalizeStringConfig(config('services.ai_document_service.token'));
        $this->connectTimeout = max(1, $this->normalizeIntConfig(config('services.ai_document_service.connect_timeout'), 10));
        $this->timeout = max(1, $this->normalizeIntConfig(config('services.ai_document_service.timeout'), 120));
    }

    /**
     * Export chat answer HTML or structured content to a download artifact.
     *
     * @return array{body: string, content_type: string, file_name: string}
     */
    public function exportContent(string $contentHtml, string $targetFormat, ?string $fileName = null): array
    {
        $payload = [
            'content_html' => $contentHtml,
            'target_format' => $targetFormat,
        ];

        if ($fileName !== null && $fileName !== '') {
            $payload['file_name'] = $fileName;
        }

        $response = Http::withToken($this->token ?: '')
            ->accept('*/*')
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->asJson()
            ->post($this->baseUrl.'/api/documents/export', $payload);

        if (! $response->successful()) {
            throw new RuntimeException($response->body() ?: 'Gagal mengekspor konten.');
        }

        return [
            'body' => (string) $response->body(),
            'content_type' => $this->mimeTypeForFormat($targetFormat),
            'file_name' => $this->buildFileName($fileName, $targetFormat),
        ];
    }

    /**
     * Extract tables from a stored document by forwarding the file to the Python service.
     *
     * @return array<string, mixed>
     */
    public function extractTables(Document $document): array
    {
        $resolvedPath = $this->resolveDocumentPath($document);

        if ($resolvedPath === null) {
            throw new RuntimeException('File dokumen tidak ditemukan.');
        }

        $contents = file_get_contents($resolvedPath);

        if ($contents === false) {
            throw new RuntimeException('Gagal membaca file dokumen.');
        }

        $response = Http::withToken($this->token ?: '')
            ->acceptJson()
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->attach('file', $contents, $document->original_name)
            ->post($this->baseUrl.'/api/documents/extract-tables');

        if (! $response->successful()) {
            throw new RuntimeException($response->body() ?: 'Gagal mengekstrak tabel.');
        }

        return $response->json() ?: [];
    }

    /**
     * Extract complete document content as HTML for DOCX/PDF exports.
     *
     * @return array<string, mixed>
     */
    public function extractContent(Document $document): array
    {
        $resolvedPath = $this->resolveDocumentPath($document);

        if ($resolvedPath === null) {
            throw new RuntimeException('File dokumen tidak ditemukan.');
        }

        $contents = file_get_contents($resolvedPath);

        if ($contents === false) {
            throw new RuntimeException('Gagal membaca file dokumen.');
        }

        $response = Http::withToken($this->token ?: '')
            ->acceptJson()
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->attach('file', $contents, $document->original_name)
            ->post($this->baseUrl.'/api/documents/extract-content');

        if (! $response->successful()) {
            throw new RuntimeException($response->body() ?: 'Gagal mengekstrak isi dokumen.');
        }

        return $response->json() ?: [];
    }

    protected function resolveDocumentPath(Document $document): ?string
    {
        $candidates = [
            $document->file_path,
            'private/'.$document->file_path,
        ];

        foreach ($candidates as $candidate) {
            if (! $candidate) {
                continue;
            }

            $absolute = Storage::disk(DocumentPreviewRenderer::DISK)->path($candidate);

            if (is_file($absolute)) {
                return $absolute;
            }
        }

        return null;
    }

    protected function mimeTypeForFormat(string $targetFormat): string
    {
        return match (strtolower(trim($targetFormat))) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv; charset=utf-8',
            default => 'application/octet-stream',
        };
    }

    protected function buildFileName(?string $fileName, string $targetFormat): string
    {
        $base = trim((string) ($fileName ?? ''));
        $base = $base !== '' ? $base : 'ista-ai-export';
        $base = preg_replace('/[^\pL\pN_.-]+/u', '-', $base) ?? $base;
        $base = trim($base, '-_.');

        if ($base === '') {
            $base = 'ista-ai-export';
        }

        return $base.'.'.strtolower(trim($targetFormat));
    }

    private function normalizeStringConfig(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        $normalized = trim((string) $value);

        if (strlen($normalized) >= 2) {
            $quote = $normalized[0];
            if (($quote === '"' || $quote === "'") && $normalized[strlen($normalized) - 1] === $quote) {
                $normalized = substr($normalized, 1, -1);
            }
        }

        return $normalized === '' ? $default : $normalized;
    }

    private function normalizeIntConfig(mixed $value, int $default): int
    {
        $normalized = $this->normalizeStringConfig($value, (string) $default);

        return is_numeric($normalized) ? (int) $normalized : $default;
    }
}
