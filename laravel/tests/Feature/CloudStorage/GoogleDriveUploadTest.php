<?php

namespace Tests\Feature\CloudStorage;

use App\Livewire\Chat\ChatIndex;
use App\Livewire\Documents\DocumentViewer;
use App\Models\CloudStorageFile;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use App\Services\CloudStorage\GoogleDriveService;
use App\Services\DocumentExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class GoogleDriveUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_answer_can_upload_exported_file_to_google_drive(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Drive answer upload',
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => "Ini jawaban untuk Drive.\n\n- Satu\n- Dua",
        ]);

        $exportService = Mockery::mock(DocumentExportService::class);
        $exportService->shouldReceive('exportContent')
            ->once()
            ->with(Mockery::on(fn (string $html): bool => str_contains($html, 'Ini jawaban untuk Drive')), 'pdf', 'ista-ai-jawaban-'.$message->id)
            ->andReturn([
                'body' => '%PDF-1.4 chat answer',
                'content_type' => 'application/pdf',
                'file_name' => 'ista-ai-jawaban-'.$message->id.'.pdf',
            ]);

        $this->app->instance(DocumentExportService::class, $exportService);

        $googleDriveService = Mockery::mock(GoogleDriveService::class);
        $googleDriveService->shouldReceive('canUploadWithConfiguredAccount')
            ->byDefault()
            ->andReturn(true);
        $googleDriveService->shouldReceive('uploadFromPath')
            ->once()
            ->with(Mockery::on(function (string $path): bool {
                return is_file($path) && file_get_contents($path) === '%PDF-1.4 chat answer';
            }), 'ista-ai-jawaban-'.$message->id.'.pdf', 'application/pdf', null)
            ->andReturn([
                'external_id' => 'drive-answer-id',
                'name' => 'ista-ai-jawaban-'.$message->id.'.pdf',
                'mime_type' => 'application/pdf',
                'web_view_link' => 'https://drive.google.com/file/d/drive-answer-id/view',
                'folder_external_id' => 'folder-upload-id',
                'size_bytes' => 2048,
            ]);

        $this->app->instance(GoogleDriveService::class, $googleDriveService);

        Livewire::actingAs($user)
            ->test(ChatIndex::class)
            ->call('saveAnswerToGoogleDrive', $message->id, 'pdf');

        $this->assertDatabaseHas('cloud_storage_files', [
            'user_id' => $user->id,
            'provider' => 'google_drive',
            'direction' => CloudStorageFile::DIRECTION_EXPORT,
            'local_type' => Message::class,
            'local_id' => $message->id,
            'external_id' => 'drive-answer-id',
            'name' => 'ista-ai-jawaban-'.$message->id.'.pdf',
            'mime_type' => 'application/pdf',
            'folder_external_id' => 'folder-upload-id',
        ]);
    }

    public function test_document_viewer_can_upload_exported_file_to_google_drive(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $document = $this->createDocument($user);

        $exportService = Mockery::mock(DocumentExportService::class);
        $exportService->shouldReceive('exportDocument')
            ->once()
            ->with(Mockery::on(fn (Document $value) => $value->is($document)), 'pdf', 'surat-keluar')
            ->andReturn([
                'body' => '%PDF-1.4 exported',
                'content_type' => 'application/pdf',
                'file_name' => 'surat-keluar.pdf',
            ]);

        $this->app->instance(DocumentExportService::class, $exportService);

        $googleDriveService = Mockery::mock(GoogleDriveService::class);
        $googleDriveService->shouldReceive('canUploadWithConfiguredAccount')
            ->byDefault()
            ->andReturn(true);
        $googleDriveService->shouldReceive('uploadFromPath')
            ->once()
            ->with(Mockery::on(function (string $path): bool {
                return is_file($path) && file_get_contents($path) === '%PDF-1.4 exported';
            }), 'surat-keluar.pdf', 'application/pdf', null)
            ->andReturn([
                'external_id' => 'drive-upload-id',
                'name' => 'surat-keluar.pdf',
                'mime_type' => 'application/pdf',
                'web_view_link' => 'https://drive.google.com/file/d/drive-upload-id/view',
                'folder_external_id' => 'folder-upload-id',
                'size_bytes' => 4096,
            ]);

        $this->app->instance(GoogleDriveService::class, $googleDriveService);

        Livewire::actingAs($user)
            ->test(DocumentViewer::class)
            ->call('open', $document->id)
            ->call('saveToGoogleDrive', 'pdf')
            ->assertSee('Tersimpan ke Google Drive', false)
            ->assertSee('Buka di Drive', false);

        $this->assertDatabaseHas('cloud_storage_files', [
            'user_id' => $user->id,
            'provider' => 'google_drive',
            'direction' => CloudStorageFile::DIRECTION_EXPORT,
            'local_type' => Document::class,
            'local_id' => $document->id,
            'external_id' => 'drive-upload-id',
            'name' => 'surat-keluar.pdf',
            'mime_type' => 'application/pdf',
            'folder_external_id' => 'folder-upload-id',
        ]);
    }

    private function createDocument(User $user): Document
    {
        return Document::create([
            'user_id' => $user->id,
            'filename' => 'surat-keluar.pdf',
            'original_name' => 'surat-keluar.pdf',
            'file_path' => 'documents/'.$user->id.'/surat-keluar.pdf',
            'source_provider' => 'local',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_READY,
        ]);
    }
}
