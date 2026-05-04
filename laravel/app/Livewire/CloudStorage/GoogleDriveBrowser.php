<?php

namespace App\Livewire\CloudStorage;

use App\Models\User;
use App\Services\CloudStorage\GoogleDriveService;
use App\Services\DocumentLifecycleService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GoogleDriveBrowser extends Component
{
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

    public bool $isConfigured = false;

    public bool $isLoading = false;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(GoogleDriveService $googleDriveService): void
    {
        $this->isConfigured = $googleDriveService->isConfigured();

        if (! $this->isConfigured) {
            return;
        }

        $rootFolderId = $googleDriveService->rootFolderId();

        if ($rootFolderId === null) {
            $this->isConfigured = false;

            return;
        }

        $this->currentFolderId = $rootFolderId;
        $this->breadcrumb = [
            [
                'id' => $rootFolderId,
                'name' => 'Folder root ISTA AI',
            ],
        ];

        $this->loadFiles($googleDriveService);
    }

    public function updatedSearch(GoogleDriveService $googleDriveService): void
    {
        $this->pageTokenStack = [];
        $this->pageToken = null;
        $this->nextPageToken = null;
        $this->loadFiles($googleDriveService);
    }

    public function goToBreadcrumb(int $index, GoogleDriveService $googleDriveService): void
    {
        if (! isset($this->breadcrumb[$index])) {
            return;
        }

        $this->breadcrumb = array_values(array_slice($this->breadcrumb, 0, $index + 1));
        $this->currentFolderId = $this->breadcrumb[$index]['id'];
        $this->pageTokenStack = [];
        $this->pageToken = null;
        $this->nextPageToken = null;
        $this->loadFiles($googleDriveService);
    }

    public function goToFolder(string $folderId, string $folderName, GoogleDriveService $googleDriveService): void
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
        $this->loadFiles($googleDriveService);
    }

    public function previousPage(GoogleDriveService $googleDriveService): void
    {
        if ($this->pageTokenStack === []) {
            return;
        }

        $this->pageToken = array_pop($this->pageTokenStack);
        $this->loadFiles($googleDriveService);
    }

    public function nextPage(GoogleDriveService $googleDriveService): void
    {
        if ($this->nextPageToken === null || $this->nextPageToken === '') {
            return;
        }

        $this->pageTokenStack[] = $this->pageToken;
        $this->pageToken = $this->nextPageToken;
        $this->loadFiles($googleDriveService);
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
            $this->statusMessage = sprintf('File "%s" berhasil diproses menjadi dokumen ISTA AI.', $document->original_name);
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function render()
    {
        $googleDriveService = app(GoogleDriveService::class);

        if (! $this->isConfigured) {
            return view('livewire.cloud-storage.google-drive-browser', [
                'isConfigured' => false,
                'items' => [],
                'breadcrumb' => [],
                'currentFolderId' => null,
                'nextPageToken' => null,
            ]);
        }

        return view('livewire.cloud-storage.google-drive-browser', [
            'isConfigured' => true,
            'items' => $this->items,
            'breadcrumb' => $this->breadcrumb,
            'currentFolderId' => $this->currentFolderId,
            'nextPageToken' => $this->nextPageToken,
            'defaultUploadFolderName' => $googleDriveService->defaultUploadFolderName(),
            'sharedDriveId' => $googleDriveService->sharedDriveId(),
        ]);
    }

    private function loadFiles(GoogleDriveService $googleDriveService): void
    {
        if (! $this->isConfigured || $this->currentFolderId === null) {
            $this->items = [];
            $this->nextPageToken = null;

            return;
        }

        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $listing = $googleDriveService->listFiles(
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
