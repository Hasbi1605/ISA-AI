<?php

namespace Tests\Feature\CloudStorage;

use App\Livewire\CloudStorage\GoogleDriveBrowser;
use App\Models\User;
use App\Services\CloudStorage\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class GoogleDriveBrowserTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_lists_files_from_the_configured_root_folder(): void
    {
        $user = User::factory()->create();

        $googleDriveService = Mockery::mock(GoogleDriveService::class);
        $googleDriveService->shouldReceive('isConfigured')->andReturn(true);
        $googleDriveService->shouldReceive('rootFolderId')->andReturn('root-folder-id');
        $googleDriveService->shouldReceive('defaultUploadFolderName')->andReturn('ISTA AI');
        $googleDriveService->shouldReceive('sharedDriveId')->andReturn(null);
        $googleDriveService->shouldReceive('listFiles')
            ->once()
            ->with('root-folder-id', '', null, 20)
            ->andReturn([
                'items' => [
                    [
                        'id' => 'folder-1',
                        'name' => 'Subfolder',
                        'mime_type' => 'application/vnd.google-apps.folder',
                        'web_view_link' => null,
                        'modified_time' => '2026-05-04T01:00:00Z',
                        'size_bytes' => null,
                        'parents' => ['root-folder-id'],
                        'is_folder' => true,
                        'is_google_workspace_file' => false,
                        'is_processable' => false,
                    ],
                    [
                        'id' => 'file-1',
                        'name' => 'arsip.pdf',
                        'mime_type' => 'application/pdf',
                        'web_view_link' => 'https://drive.google.com/file/d/file-1/view',
                        'modified_time' => '2026-05-04T01:00:00Z',
                        'size_bytes' => 2048,
                        'parents' => ['root-folder-id'],
                        'is_folder' => false,
                        'is_google_workspace_file' => false,
                        'is_processable' => true,
                    ],
                ],
                'next_page_token' => 'page-2',
                'folder_id' => 'root-folder-id',
                'folder_name' => 'root-folder-id',
            ]);

        $this->app->instance(GoogleDriveService::class, $googleDriveService);

        Livewire::actingAs($user)
            ->test(GoogleDriveBrowser::class)
            ->assertSee('Folder root ISTA AI', false)
            ->assertSee('Subfolder', false)
            ->assertSee('arsip.pdf', false)
            ->assertSee('Buka Folder', false)
            ->assertSee('Proses dengan AI', false)
            ->assertSet('nextPageToken', 'page-2');
    }
}
