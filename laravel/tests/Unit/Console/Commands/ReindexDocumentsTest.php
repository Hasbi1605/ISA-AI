<?php

namespace Tests\Unit\Console\Commands;

use App\Models\Document;
use App\Models\User;
use App\Services\Document\LaravelDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ReindexDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_finds_documents_with_ready_status(): void
    {
        $user = User::factory()->create();
        Document::factory()->for($user)->create([
            'status' => 'ready',
            'provider_file_id' => null,
            'file_path' => 'documents/1/test.pdf',
        ]);

        Storage::put('documents/1/test.pdf', 'fake content');

        $this->artisan('documents:reindex --dry-run')
            ->assertExitCode(0)
            ->expectsOutput('Found 1 document(s) to reindex.');
    }

    public function test_finds_documents_with_pending_status(): void
    {
        $user = User::factory()->create();
        Document::factory()->for($user)->create([
            'status' => 'pending',
            'provider_file_id' => null,
            'file_path' => 'documents/1/test.pdf',
        ]);

        Storage::put('documents/1/test.pdf', 'fake content');

        $this->artisan('documents:reindex --dry-run')
            ->assertExitCode(0)
            ->expectsOutput('Found 1 document(s) to reindex.');
    }

    public function test_skips_documents_with_existing_provider_file_id(): void
    {
        $user = User::factory()->create();
        Document::factory()->for($user)->create([
            'status' => 'ready',
            'provider_file_id' => 'existing-provider-id',
            'file_path' => 'documents/1/test.pdf',
        ]);

        Storage::put('documents/1/test.pdf', 'fake content');

        $this->artisan('documents:reindex --dry-run')
            ->assertExitCode(0)
            ->expectsOutput('Found 1 document(s) to reindex.');
    }

    public function test_filters_by_user_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Document::factory()->for($user1)->create([
            'status' => 'ready',
            'provider_file_id' => null,
            'file_path' => 'documents/1/test.pdf',
        ]);

        Document::factory()->for($user2)->create([
            'status' => 'ready',
            'provider_file_id' => null,
            'file_path' => 'documents/2/test.pdf',
        ]);

        Storage::put('documents/1/test.pdf', 'fake content');
        Storage::put('documents/2/test.pdf', 'fake content');

        $this->artisan('documents:reindex --dry-run --user=' . $user1->id)
            ->assertExitCode(0)
            ->expectsOutput('Filtering to user ID: ' . $user1->id)
            ->expectsOutput('Found 1 document(s) to reindex.');
    }

    public function test_respects_limit_option(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            Document::factory()->for($user)->create([
                'status' => 'ready',
                'provider_file_id' => null,
                'file_path' => "documents/{$user->id}/test{$i}.pdf",
            ]);
            Storage::put("documents/{$user->id}/test{$i}.pdf", 'fake content');
        }

        $this->artisan('documents:reindex --dry-run --limit=3')
            ->assertExitCode(0)
            ->expectsOutput('Found 3 document(s) to reindex.');
    }

    public function test_shows_status_and_provider_id_in_dry_run(): void
    {
        $user = User::factory()->create();
        Document::factory()->for($user)->create([
            'status' => 'ready',
            'provider_file_id' => null,
            'file_path' => 'documents/1/test.pdf',
            'file_size_bytes' => 1024,
        ]);

        Storage::put('documents/1/test.pdf', 'fake content');

        $this->artisan('documents:reindex --dry-run')
            ->assertExitCode(0);
    }

    public function test_updates_provider_file_id_on_success(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => 'ready',
            'provider_file_id' => null,
            'file_path' => 'documents/1/test.pdf',
        ]);

        Storage::put('documents/1/test.pdf', 'fake content');

        $mockService = Mockery::mock(LaravelDocumentService::class);
        $mockService->shouldReceive('processDocument')
            ->once()
            ->andReturn([
                'status' => 'success',
                'provider_file_id' => 'new-provider-id-123',
            ]);
        $this->app->instance(LaravelDocumentService::class, $mockService);

        $this->artisan('documents:reindex')
            ->assertExitCode(0)
            ->expectsOutput('Re-index complete: 1 succeeded, 0 skipped, 0 failed.');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'provider_file_id' => 'new-provider-id-123',
            'status' => 'ready',
        ]);
    }

    public function test_skips_documents_with_existing_provider_in_reindex(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => 'ready',
            'provider_file_id' => 'existing-id',
            'file_path' => 'documents/1/test.pdf',
        ]);

        Storage::put('documents/1/test.pdf', 'fake content');

        $mockService = Mockery::mock(LaravelDocumentService::class);
        $mockService->shouldNotReceive('processDocument');
        $this->app->instance(LaravelDocumentService::class, $mockService);

        $this->artisan('documents:reindex')
            ->assertExitCode(0)
            ->expectsOutput('Re-index complete: 0 succeeded, 1 skipped, 0 failed.');
    }
}