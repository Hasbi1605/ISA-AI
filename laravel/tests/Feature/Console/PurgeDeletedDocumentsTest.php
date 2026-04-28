<?php

namespace Tests\Feature\Console;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeDeletedDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_command_force_deletes_documents_past_retention_window(): void
    {
        Storage::fake('local');
        config()->set('services.ai_document_service.url', 'http://python-ai-docs:8002');

        $user = User::factory()->create();
        $filePath = 'documents/' . $user->id . '/purge.pdf';
        Storage::disk('local')->put($filePath, 'dummy content');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'purge.pdf',
            'original_name' => 'purge.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'ready',
        ]);

        $document->delete();
        Document::withTrashed()->whereKey($document->id)->update([
            'deleted_at' => now()->subDays(8),
        ]);

        Http::fake([
            '*' => Http::response(['message' => 'success'], 200),
        ]);

        $this->artisan('documents:purge-deleted', ['--days' => 7])
            ->expectsOutput('Purged 1 document(s) soft-deleted for at least 7 day(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($filePath);
        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && $request->url() === 'http://python-ai-docs:8002/api/documents/purge.pdf';
        });
    }

    public function test_purge_command_keeps_recent_soft_deleted_documents(): void
    {
        Storage::fake('local');
        config()->set('services.ai_document_service.url', 'http://python-ai-docs:8002');

        $user = User::factory()->create();
        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'keep.pdf',
            'original_name' => 'keep.pdf',
            'file_path' => 'documents/' . $user->id . '/keep.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'ready',
        ]);

        $document->delete();
        Document::withTrashed()->whereKey($document->id)->update([
            'deleted_at' => now()->subDays(6),
        ]);

        Http::fake([
            '*' => Http::response(['message' => 'success'], 200),
        ]);

        $this->artisan('documents:purge-deleted', ['--days' => 7])
            ->expectsOutput('Purged 0 document(s) soft-deleted for at least 7 day(s).')
            ->assertExitCode(0);

        $this->assertSoftDeleted($document);
        Http::assertNothingSent();
    }

    public function test_deleted_documents_purge_is_registered_in_schedule(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('documents:purge-deleted --days=7')
            ->assertExitCode(0);
    }
}
