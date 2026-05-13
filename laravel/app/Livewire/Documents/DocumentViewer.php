<?php

namespace App\Livewire\Documents;

use App\Models\CloudStorageFile;
use App\Models\Document;
use App\Services\CloudStorage\GoogleDriveService;
use App\Services\DocumentExportService;
use App\Services\Documents\DocumentPreviewRenderer;
use App\Support\UserFacingError;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class DocumentViewer extends Component
{
    public ?int $documentId = null;

    public bool $isOpen = false;

    public ?array $driveUploadResult = null;

    public ?string $driveUploadError = null;

    public function open(int $documentId): void
    {
        $this->documentId = $documentId;
        $this->isOpen = true;
        $this->driveUploadResult = null;
        $this->driveUploadError = null;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->documentId = null;
        $this->driveUploadResult = null;
        $this->driveUploadError = null;
    }

    public function saveToGoogleDrive(string $targetFormat, ?string $folderExternalId = null): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            $this->driveUploadError = 'Anda harus login terlebih dahulu.';

            return;
        }

        $document = $this->resolveDocument();

        if ($document === null) {
            $this->driveUploadError = 'Dokumen tidak ditemukan.';

            return;
        }

        try {
            $exportService = app(DocumentExportService::class);
            $artifact = $exportService->exportDocument(
                $document,
                $targetFormat,
                pathinfo((string) ($document->original_name ?: $document->filename), PATHINFO_FILENAME),
            );

            $tempRelativePath = 'tmp/cloud/google-drive/'.Str::uuid().'.'.strtolower(trim($targetFormat));
            Storage::disk('local')->put($tempRelativePath, $artifact['body']);
            $tempAbsolutePath = Storage::disk('local')->path($tempRelativePath);

            try {
                $driveService = app(GoogleDriveService::class);
                $upload = $driveService->uploadFromPath(
                    $tempAbsolutePath,
                    $artifact['file_name'],
                    $artifact['content_type'],
                    $folderExternalId,
                );

                CloudStorageFile::create([
                    'user_id' => (int) $userId,
                    'provider' => 'google_drive',
                    'direction' => CloudStorageFile::DIRECTION_EXPORT,
                    'local_type' => Document::class,
                    'local_id' => $document->id,
                    'external_id' => $upload['external_id'],
                    'name' => $upload['name'],
                    'mime_type' => $upload['mime_type'],
                    'web_view_link' => $upload['web_view_link'],
                    'folder_external_id' => $upload['folder_external_id'],
                    'size_bytes' => $upload['size_bytes'],
                    'synced_at' => now(),
                ]);

                $this->driveUploadResult = [
                    'file_name' => $upload['name'],
                    'web_view_link' => $upload['web_view_link'],
                    'folder_external_id' => $upload['folder_external_id'],
                ];
                $this->driveUploadError = null;
            } finally {
                Storage::disk('local')->delete($tempRelativePath);
            }
        } catch (\Throwable $e) {
            report($e);
            $this->driveUploadError = UserFacingError::message($e, 'Upload ke Google Drive gagal. Coba lagi atau hubungi admin bila berulang.');
            $this->driveUploadResult = null;
        }
    }

    public function render()
    {
        $document = $this->resolveDocument();
        $kind = null;
        $previewStatus = null;
        $streamUrl = null;
        $htmlUrl = null;
        $pdfPreviewAvailable = false;

        if ($document !== null) {
            $renderer = app(DocumentPreviewRenderer::class);
            $previewStatus = $document->preview_status;

            $kind = match (true) {
                $renderer->isPdf($document) => 'pdf',
                $renderer->isDocx($document) => 'docx',
                $renderer->isXlsx($document) => 'xlsx',
                $renderer->isCsv($document) => 'csv',
                default => 'unknown',
            };

            if ($kind === 'pdf') {
                $streamUrl = route('documents.preview.stream', $document);
                $pdfPreviewAvailable = $this->resolveSourcePath($document) !== null;
            } elseif (in_array($kind, ['docx', 'xlsx', 'csv'], true)) {
                $htmlUrl = route('documents.preview.html', $document);
            }
        }

        return view('livewire.documents.document-viewer', [
            'document' => $document,
            'kind' => $kind,
            'previewStatus' => $previewStatus,
            'streamUrl' => $streamUrl,
            'htmlUrl' => $htmlUrl,
            'pdfPreviewAvailable' => $pdfPreviewAvailable,
            'googleDriveUploadAvailable' => app(GoogleDriveService::class)->canUploadWithConfiguredAccount(),
        ]);
    }

    protected function resolveSourcePath(Document $document): ?string
    {
        foreach ([$document->file_path, 'private/'.$document->file_path] as $candidate) {
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

    private function resolveDocument(): ?Document
    {
        if (! $this->isOpen || $this->documentId === null) {
            return null;
        }

        return Document::query()
            ->where('user_id', Auth::id())
            ->find($this->documentId);
    }
}
