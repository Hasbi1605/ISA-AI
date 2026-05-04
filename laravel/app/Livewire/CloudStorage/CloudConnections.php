<?php

namespace App\Livewire\CloudStorage;

use App\Services\CloudStorage\GoogleDriveService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CloudConnections extends Component
{
    public function render()
    {
        $googleDriveService = app(GoogleDriveService::class);

        return view('livewire.cloud-storage.cloud-connections', [
            'isConfigured' => $googleDriveService->isConfigured(),
            'rootFolderId' => $googleDriveService->rootFolderId(),
            'sharedDriveId' => $googleDriveService->sharedDriveId(),
            'defaultUploadFolderName' => $googleDriveService->defaultUploadFolderName(),
        ]);
    }
}
