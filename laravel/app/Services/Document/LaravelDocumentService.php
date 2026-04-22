<?php

namespace App\Services\Document;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AiManager;
use Laravel\Ai\AnonymousAgent;

class LaravelDocumentService
{
    protected AiManager $ai;

    public function __construct()
    {
        $this->ai = app(AiManager::class);
    }

    public function processDocument(string $filePath, string $originalName, int $userId): array
    {
        if (!config('ai.laravel_ai.document_process_enabled', false)) {
            return [
                'status' => 'error',
                'message' => 'Document process belum diaktifkan.',
            ];
        }

        try {
            $provider = $this->ai->documentProcessor();

            $result = $provider->process(
                file: $filePath,
                originalFilename: $originalName,
                userId: (string) $userId
            );

            Log::info('LaravelDocumentService: document processed', [
                'file' => $originalName,
                'provider_id' => $result->id ?? null,
            ]);

            return [
                'status' => 'success',
                'message' => 'Dokumen berhasil diproses',
                'provider_id' => $result->id ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('LaravelDocumentService: process failed', [
                'file' => $originalName,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Gagal memproses dokumen: ' . $e->getMessage(),
            ];
        }
    }

    public function summarizeDocument(string $filename, ?string $user_id = null): array
    {
        if (!config('ai.laravel_ai.document_summarize_enabled', false)) {
            return [
                'status' => 'error',
                'message' => 'Document summarize belum diaktifkan.',
            ];
        }

        try {
            $provider = $this->ai->textProvider();

            $document = Document::where('original_name', $filename)->first();

            if (!$document) {
                return [
                    'status' => 'error',
                    'message' => 'Dokumen tidak ditemukan.',
                ];
            }

            $attachments = array_filter([$document->file_path]);

            $agent = AnonymousAgent::make(
                instructions: 'Anda adalah asisten yang merangkum dokumen. Berikan ringkasan singkat dan akurat dari dokumen yang diberikan.'
            );

            $result = $provider->agent(
                agent: $agent,
                prompt: "Rangkum dokumen berikut: {$filename}",
                attachments: $attachments,
            );

            $content = $result->content ?? $result->text ?? '';

            Log::info('LaravelDocumentService: document summarized', [
                'file' => $filename,
                'content_length' => strlen($content),
            ]);

            return [
                'status' => 'success',
                'summary' => $content,
            ];
        } catch (\Throwable $e) {
            Log::error('LaravelDocumentService: summarize failed', [
                'file' => $filename,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Gagal merangkum dokumen: ' . $e->getMessage(),
            ];
        }
    }

    public function deleteDocument(string $filename): bool
    {
        try {
            $document = Document::where('original_name', $filename)->first();

            if ($document) {
                $document->delete();
            }

            Log::info('LaravelDocumentService: document deleted', [
                'file' => $filename,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('LaravelDocumentService: delete failed', [
                'file' => $filename,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}