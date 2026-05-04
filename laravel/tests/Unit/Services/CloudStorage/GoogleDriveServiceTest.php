<?php

namespace Tests\Unit\Services\CloudStorage;

use App\Services\CloudStorage\GoogleDriveService;
use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    public function test_service_reports_configured_when_credentials_and_root_folder_exist(): void
    {
        config([
            'services.google_drive.service_account_json' => json_encode([
                'type' => 'service_account',
                'client_email' => 'ista-ai@example.test',
            ]),
            'services.google_drive.root_folder_id' => 'root-folder-id',
            'services.google_drive.default_upload_folder_name' => 'ISTA AI',
        ]);

        $service = app(GoogleDriveService::class);

        $this->assertTrue($service->isConfigured());
        $this->assertSame('root-folder-id', $service->rootFolderId());
        $this->assertSame('ISTA AI', $service->defaultUploadFolderName());
    }

    public function test_service_reports_unconfigured_when_credentials_are_missing(): void
    {
        config([
            'services.google_drive.service_account_json' => null,
            'services.google_drive.service_account_path' => null,
            'services.google_drive.root_folder_id' => 'root-folder-id',
        ]);

        $service = app(GoogleDriveService::class);

        $this->assertFalse($service->isConfigured());
    }

    public function test_empty_shared_drive_id_is_treated_as_null(): void
    {
        config([
            'services.google_drive.shared_drive_id' => '',
        ]);

        $service = app(GoogleDriveService::class);

        $this->assertNull($service->sharedDriveId());
    }
}
