<?php

namespace App\Services;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Services\Documents\DocumentPreviewRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DocumentLifecycleService
{
    public const MAX_DOCUMENTS_PER_USER = 10;
    public const SOFT_DELETE_RETENTION_DAYS = 7;
    
    public const ALLOWED_ATTACHMENT_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Upload and initiate document processing
     *
     * @param UploadedFile $file
     * @param int $userId
     * @return Document
     * @throws ValidationException
     * @throws \Exception
     */
    public function uploadDocument(UploadedFile $file, int $userId): Document
    {
        // 1. Check Document Limit
        $count = Document::where('user_id', $userId)->count();
        if ($count >= self::MAX_DOCUMENTS_PER_USER) {
            throw ValidationException::withMessages([
                'file' => 'Limit kuota dokumen tercapai (Maksimal 10 dokumen).',
            ]);
        }

        // 2. Validate MIME Type
        $originalName = $file->getClientOriginalName();
        $detectedMimeType = (string) $file->getMimeType();

        if (!in_array($detectedMimeType, self::ALLOWED_ATTACHMENT_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => 'Tipe MIME file tidak valid. Gunakan PDF, DOCX, atau XLSX.',
            ]);
        }

        // 3. Check for duplicates
        $duplicateExists = Document::where('user_id', $userId)
            ->where('original_name', $originalName)
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'file' => 'File dengan nama yang sama sudah pernah diunggah.',
            ]);
        }

        // 4. Store file
        $filename = time() . '_' . $file->hashName();
        $filePath = $file->storeAs('documents/' . $userId, $filename);

        if (!$filePath) {
            throw new \Exception("Gagal menyimpan file ke storage.");
        }

        // 5. Create Database Record
        $document = Document::create([
            'user_id' => $userId,
            'filename' => $filename,
            'original_name' => $originalName,
            'file_path' => $filePath,
            'mime_type' => $detectedMimeType,
            'file_size_bytes' => $file->getSize(),
            'status' => 'pending',
        ]);

        // 6. Dispatch Processing Job
        $this->dispatchProcessing($document);

        return $document;
    }

    /**
     * Dispatch the job to process a document
     *
     * @param Document $document
     * @return void
     */
    public function dispatchProcessing(Document $document): void
    {
        ProcessDocument::dispatch($document);
    }

    /**
     * Delete document and perform cleanup (Vector DB, Storage, Database)
     *
     * @param Document $document
     * @return bool
     * @throws \Exception
     */
    public function deleteDocument(Document $document): bool
    {
        $this->cleanupDocumentArtifacts($document);

        // 3. Delete database record (Soft Delete)
        return $document->delete();
    }
    
    /**
     * Delete multiple documents
     * 
     * @param iterable<Document> $documents
     * @return void
     */
    public function deleteDocuments(iterable $documents): void
    {
        foreach ($documents as $document) {
            $this->deleteDocument($document);
        }
    }

    public function purgeSoftDeletedDocuments(int $retentionDays = self::SOFT_DELETE_RETENTION_DAYS): int
    {
        $purgedDocuments = 0;
        $cutoff = now()->subDays($retentionDays);

        foreach (
            Document::onlyTrashed()
                ->where('deleted_at', '<=', $cutoff)
                ->lazyById(100) as $document
        ) {
            $this->cleanupDocumentArtifacts($document);

            if ($document->forceDelete()) {
                $purgedDocuments++;
            }
        }

        return $purgedDocuments;
    }

    /**
     * Summarize a document, with readiness guard
     *
     * @param Document $document
     * @param AIService $aiService
     * @return array
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    public function summarizeDocument(Document $document, AIService $aiService): array
    {
        if ($document->status !== 'ready') {
            throw new InvalidArgumentException('Dokumen belum selesai diproses. Tunggu hingga status menjadi "ready".');
        }

        return $aiService->summarizeDocument($document->original_name, (string) $document->user_id);
    }

    private function cleanupDocumentArtifacts(Document $document): void
    {
        $this->deleteDocumentVectors($document);
        $this->deleteDocumentFile($document);
        $this->deleteDocumentPreview($document);
    }

    private function deleteDocumentPreview(Document $document): void
    {
        try {
            app(DocumentPreviewRenderer::class)->deletePreview($document);
        } catch (\Throwable $e) {
            logger()->warning("Preview cleanup failed for document {$document->id}, proceeding anyway: " . $e->getMessage());
        }
    }

    private function deleteDocumentVectors(Document $document): void
    {
        $pythonUrl = rtrim((string) config('services.ai_document_service.url', config('services.ai_service.url', 'http://127.0.0.1:8001')), '/')
            . '/api/documents/' . urlencode($document->original_name);
        $token = config('services.ai_document_service.token', config('services.ai_service.token'));

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->delete($pythonUrl);

            if (!$response->successful() && $response->status() !== 404) {
                logger()->warning("Vector deletion failed for {$document->original_name}, proceeding anyway: " . $response->body());
            }
        } catch (\Exception $e) {
            logger()->warning("Vector deletion HTTP request failed for {$document->original_name}, proceeding anyway: " . $e->getMessage());
        }
    }

    private function deleteDocumentFile(Document $document): void
    {
        if ($document->file_path && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }
    }
}
