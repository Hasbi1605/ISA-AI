<?php

namespace App\Services;

use App\Jobs\ProcessDocument;
use App\Jobs\RenderDocumentPreview;
use App\Models\CloudStorageFile;
use App\Models\Document;
use App\Models\User;
use App\Services\CloudStorage\GoogleDriveService;
use App\Services\Documents\DocumentPreviewRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DocumentLifecycleService
{
    public const MAX_DOCUMENTS_PER_USER = 10;

    public const MAX_DOCUMENT_SIZE_KILOBYTES = 51_200;

    public const MAX_DOCUMENT_SIZE_BYTES = self::MAX_DOCUMENT_SIZE_KILOBYTES * 1024;

    public const SOFT_DELETE_RETENTION_DAYS = 7;

    /**
     * Upload and initiate document processing
     *
     * @throws ValidationException
     * @throws \Exception
     */
    public function uploadDocument(UploadedFile $file, int $userId, array $sourceAttributes = []): Document
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

        if (! in_array($detectedMimeType, Document::attachmentMimeTypes(), true)) {
            throw ValidationException::withMessages([
                'file' => 'Tipe MIME file tidak valid. Gunakan PDF, DOCX, XLSX, atau CSV.',
            ]);
        }

        $fileSizeBytes = $file->getSize();

        if ($fileSizeBytes !== null && $fileSizeBytes > self::MAX_DOCUMENT_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'Ukuran file melebihi batas 50 MB.',
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
        $filename = time().'_'.$file->hashName();
        $filePath = $file->storeAs('documents/'.$userId, $filename);

        if (! $filePath) {
            throw new \Exception('Gagal menyimpan file ke storage.');
        }

        // 5. Create Database Record
        $document = Document::create([
            'user_id' => $userId,
            'filename' => $filename,
            'original_name' => $originalName,
            'file_path' => $filePath,
            'source_provider' => $sourceAttributes['source_provider'] ?? 'local',
            'source_external_id' => $sourceAttributes['source_external_id'] ?? null,
            'source_synced_at' => $sourceAttributes['source_synced_at'] ?? null,
            'mime_type' => $detectedMimeType,
            'file_size_bytes' => $fileSizeBytes,
            'status' => 'pending',
        ]);

        // 6. Dispatch preview and processing jobs on separate queues.
        try {
            $this->dispatchPreviewRendering($document);
        } catch (\Throwable $e) {
            logger()->warning("Preview dispatch failed for document {$document->id}, proceeding: ".$e->getMessage());
        }
        $this->dispatchProcessing($document);

        return $document;
    }

    /**
     * Download a file from Google Drive and ingest it into the normal document pipeline.
     */
    public function ingestFromCloud(User $user, string $provider, string $externalId): Document
    {
        if ($provider !== 'google_drive') {
            throw new InvalidArgumentException('Provider cloud storage tidak didukung.');
        }

        $existingDocument = Document::query()
            ->where('user_id', $user->id)
            ->where('source_provider', $provider)
            ->where('source_external_id', $externalId)
            ->first();

        if ($existingDocument !== null) {
            throw ValidationException::withMessages([
                'file' => 'File Drive ini sudah pernah diproses di akun Anda.',
            ]);
        }

        /** @var GoogleDriveService $driveService */
        $driveService = app(GoogleDriveService::class);
        $download = $driveService->downloadToTemp($externalId);
        $tempPath = $download['path'] ?? null;

        if (! is_string($tempPath) || $tempPath === '' || ! is_file($tempPath)) {
            throw new \RuntimeException('Gagal menyiapkan file sementara dari Google Drive.');
        }

        $originalName = (string) ($download['original_name'] ?? basename($tempPath));
        $mimeType = (string) ($download['mime_type'] ?? 'application/octet-stream');
        $sourceSyncedAt = now();

        try {
            $uploadedFile = new UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true
            );

            $document = $this->uploadDocument($uploadedFile, $user->id, [
                'source_provider' => $provider,
                'source_external_id' => $externalId,
                'source_synced_at' => $sourceSyncedAt,
            ]);

            CloudStorageFile::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'direction' => CloudStorageFile::DIRECTION_IMPORT,
                'local_type' => Document::class,
                'local_id' => $document->id,
                'external_id' => $externalId,
                'name' => $originalName,
                'mime_type' => $mimeType,
                'web_view_link' => $download['web_view_link'] ?? null,
                'folder_external_id' => $download['folder_external_id'] ?? null,
                'size_bytes' => $download['size_bytes'] ?? null,
                'synced_at' => $sourceSyncedAt,
            ]);

            return $document;
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Dispatch the job to process a document
     */
    public function dispatchProcessing(Document $document): void
    {
        ProcessDocument::dispatch($document);
    }

    /**
     * Dispatch the job to render a document preview.
     */
    public function dispatchPreviewRendering(Document $document): void
    {
        RenderDocumentPreview::dispatch($document);
    }

    /**
     * Delete document and perform cleanup (Vector DB, Storage, Database)
     *
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
     * @param  iterable<Document>  $documents
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
            logger()->warning("Preview cleanup failed for document {$document->id}, proceeding anyway: ".$e->getMessage());
        }
    }

    private function deleteDocumentVectors(Document $document): void
    {
        $pythonUrl = rtrim((string) config('services.ai_document_service.url', config('services.ai_service.url', 'http://127.0.0.1:8001')), '/')
            .'/api/documents/'.urlencode($document->original_name)
            .'?'.http_build_query(['user_id' => (string) $document->user_id]);
        $token = config('services.ai_document_service.token', config('services.ai_service.token'));

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->delete($pythonUrl);

            if (! $response->successful() && $response->status() !== 404) {
                logger()->warning("Vector deletion failed for {$document->original_name}, proceeding anyway: ".$response->body());
            }
        } catch (\Exception $e) {
            logger()->warning("Vector deletion HTTP request failed for {$document->original_name}, proceeding anyway: ".$e->getMessage());
        }
    }

    private function deleteDocumentFile(Document $document): void
    {
        if ($document->file_path && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }
    }
}
