<?php

namespace Tests\Unit\Services\CloudStorage;

use App\Services\CloudStorage\GoogleDriveService;
use Google\Service\Exception as GoogleServiceException;
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

    public function test_empty_impersonated_user_email_is_treated_as_null(): void
    {
        config([
            'services.google_drive.impersonated_user_email' => '',
        ]);

        $service = app(GoogleDriveService::class);

        $this->assertNull($service->impersonatedUserEmail());
    }

    public function test_uploads_require_shared_drive_or_central_oauth_connection(): void
    {
        config([
            'services.google_drive.shared_drive_id' => null,
            'services.google_drive.impersonated_user_email' => null,
            'services.google_drive.root_folder_id' => 'my-drive-folder-id',
        ]);

        $service = app(GoogleDriveService::class);
        $tempPath = tempnam(sys_get_temp_dir(), 'gdrive-upload-test-');

        $this->assertIsString($tempPath);
        file_put_contents($tempPath, 'content');

        try {
            $this->assertFalse($service->canUploadWithConfiguredAccount());
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Upload ke Google Drive belum aktif. Hubungkan akun Google Drive pusat lewat OAuth atau gunakan Shared Drive kantor.');

            $service->uploadFromPath($tempPath, 'answer.pdf', 'application/pdf');
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_impersonated_user_email_enables_server_side_uploads(): void
    {
        config([
            'services.google_drive.shared_drive_id' => null,
            'services.google_drive.impersonated_user_email' => 'drive-owner@example.test',
        ]);

        $service = app(GoogleDriveService::class);

        $this->assertSame('drive-owner@example.test', $service->impersonatedUserEmail());
        $this->assertTrue($service->canUploadWithConfiguredAccount());
    }

    public function test_upload_error_message_is_sanitized_for_service_account_quota_errors(): void
    {
        $service = app(GoogleDriveService::class);

        $throwable = new GoogleServiceException(
            '{"error":{"code":403,"message":"Service Accounts do not have storage quota. Leverage shared drives, or use OAuth delegation instead.","errors":[{"message":"Service Accounts do not have storage quota. Leverage shared drives, or use OAuth delegation instead.","domain":"usageLimits","reason":"storageQuotaExceeded"}]}}',
            403,
            null,
            [
                [
                    'message' => 'Service Accounts do not have storage quota. Leverage shared drives, or use OAuth delegation instead.',
                    'domain' => 'usageLimits',
                    'reason' => 'storageQuotaExceeded',
                ],
            ]
        );

        $this->assertSame(
            'Upload ke Google Drive belum aktif. Hubungkan akun Google Drive pusat lewat OAuth atau gunakan Shared Drive kantor.',
            $service->describeUploadFailure($throwable),
        );
    }

    public function test_upload_error_message_extracts_google_api_message_without_raw_json(): void
    {
        $service = app(GoogleDriveService::class);

        $throwable = new \RuntimeException('{"error":{"code":404,"message":"Folder tujuan tidak ditemukan.","errors":[{"message":"Folder tujuan tidak ditemukan.","domain":"global","reason":"notFound"}]}}');

        $this->assertSame('Folder tujuan tidak ditemukan.', $service->describeUploadFailure($throwable));
    }
}
