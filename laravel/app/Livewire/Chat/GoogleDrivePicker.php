<?php

namespace App\Livewire\Chat;

use App\Models\User;
use App\Services\CloudStorage\GoogleDriveService;
use App\Services\DocumentLifecycleService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GoogleDrivePicker extends Component
{
    public bool $isOpen = false;

    public bool $isConfigured = false;

    public string $search = '';

    public ?string $currentFolderId = null;

    /**
     * @var array<int, array{id: string, name: string}>
     */
    public array $breadcrumb = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    public ?string $nextPageToken = null;

    public ?string $pageToken = null;

    /**
     * @var array<int, ?string>
     */
    public array $pageTokenStack = [];

    public bool $isLoading = false;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function open(): void
    {
        $this->isOpen = true;
        $this->statusMessage = null;
        $this->errorMessage = null;

        $this->initializeDriveState();
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function updatedSearch(): void
    {
        $this->pageTokenStack = [];
        $this->pageToken = null;
        $this->nextPageToken = null;
        $this->loadFiles();
    }

    public function goToBreadcrumb(int $index): void
    {
        if (! isset($this->breadcrumb[$index])) {
            return;
        }

        $this->breadcrumb = array_values(array_slice($this->breadcrumb, 0, $index + 1));
        $this->currentFolderId = $this->breadcrumb[$index]['id'];
        $this->pageTokenStack = [];
        $this->pageToken = null;
        $this->nextPageToken = null;
        $this->loadFiles();
    }

    public function goToFolder(string $folderId, string $folderName): void
    {
        if (! $this->isConfigured) {
            return;
        }

        $this->breadcrumb[] = [
            'id' => $folderId,
            'name' => $folderName,
        ];
        $this->currentFolderId = $folderId;
        $this->pageTokenStack = [];
        $this->pageToken = null;
        $this->nextPageToken = null;
        $this->loadFiles();
    }

    public function previousPage(): void
    {
        if ($this->pageTokenStack === []) {
            return;
        }

        $this->pageToken = array_pop($this->pageTokenStack);
        $this->loadFiles();
    }

    public function nextPage(): void
    {
        if ($this->nextPageToken === null || $this->nextPageToken === '') {
            return;
        }

        $this->pageTokenStack[] = $this->pageToken;
        $this->pageToken = $this->nextPageToken;
        $this->loadFiles();
    }

    public function processFile(string $fileId, DocumentLifecycleService $documentLifecycleService): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            $this->errorMessage = 'Anda harus login terlebih dahulu.';

            return;
        }

        try {
            $document = $documentLifecycleService->ingestFromCloud($user, 'google_drive', $fileId);
            $this->statusMessage = sprintf('File "%s" berhasil ditambahkan dari Google Drive.', $document->original_name);
            $this->errorMessage = null;
            $this->isOpen = false;

            $this->dispatch('google-drive-document-imported', documentId: $document->id);
            $this->dispatch('open-sidebar-right');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function render()
    {
        $defaultUploadFolderName = 'ISTA AI';
        $sharedDriveId = null;

        if ($this->isConfigured) {
            $googleDriveService = app(GoogleDriveService::class);
            $defaultUploadFolderName = $googleDriveService->defaultUploadFolderName();
            $sharedDriveId = $googleDriveService->sharedDriveId();
        }

        return view('livewire.chat.google-drive-picker', [
            'items' => $this->items,
            'breadcrumb' => $this->breadcrumb,
            'nextPageToken' => $this->nextPageToken,
            'defaultUploadFolderName' => $defaultUploadFolderName,
            'sharedDriveId' => $sharedDriveId,
        ]);
    }

    private function initializeDriveState(): void
    {
        $googleDriveService = app(GoogleDriveService::class);
        $this->isConfigured = $googleDriveService->isConfigured();

        if (! $this->isConfigured) {
            $this->items = [];
            $this->breadcrumb = [];
            $this->currentFolderId = null;
            $this->nextPageToken = null;

            return;
        }

        $rootFolderId = $googleDriveService->rootFolderId();

        if ($rootFolderId === null) {
            $this->isConfigured = false;
            $this->items = [];
            $this->breadcrumb = [];
            $this->currentFolderId = null;
            $this->nextPageToken = null;

            return;
        }

        if ($this->currentFolderId === null) {
            $this->currentFolderId = $rootFolderId;
            $this->breadcrumb = [
                [
                    'id' => $rootFolderId,
                    'name' => 'Folder root ISTA AI',
                ],
            ];
        }

        $this->loadFiles();
    }

    private function loadFiles(): void
    {
        if (! $this->isOpen || ! $this->isConfigured || $this->currentFolderId === null) {
            $this->items = [];
            $this->nextPageToken = null;

            return;
        }

        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $listing = app(GoogleDriveService::class)->listFiles(
                $this->currentFolderId,
                $this->search,
                $this->pageToken,
                20,
            );

            $this->items = $listing['items'];
            $this->nextPageToken = $listing['next_page_token'];
        } catch (\Throwable $e) {
            $this->items = [];
            $this->nextPageToken = null;
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isLoading = false;
        }
    }
}
