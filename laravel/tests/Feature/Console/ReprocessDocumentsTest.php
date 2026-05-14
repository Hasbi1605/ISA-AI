<?php

namespace Tests\Feature\Console;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class ReprocessDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprocess_command_dispatches_jobs_via_lifecycle_service()
    {
        Queue::fake();

        $user = User::factory()->create();
        $doc = Document::create([
            'user_id' => $user->id,
            'filename' => 'doc.pdf',
            'original_name' => 'doc.pdf',
            'file_path' => 'path/doc.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'ready',
        ]);

        $this->artisan('documents:reprocess', ['--id' => $doc->id])
            ->assertExitCode(0);

        Queue::assertPushed(ProcessDocument::class, function ($job) use ($doc) {
            return $job->document->id === $doc->id;
        });
        
        $this->assertEquals('pending', $doc->fresh()->status);
    }
    
    public function test_reprocess_command_uses_document_lifecycle_service()
    {
        $user = User::factory()->create();
        $doc = Document::create([
            'user_id' => $user->id,
            'filename' => 'doc.pdf',
            'original_name' => 'doc.pdf',
            'file_path' => 'path/doc.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'ready',
        ]);

        $this->mock(DocumentLifecycleService::class, function (MockInterface $mock) use ($doc) {
            $mock->shouldReceive('dispatchProcessing')
                ->once()
                ->withArgs(function ($documentArg) use ($doc) {
                    return $documentArg->id === $doc->id;
                });
        });

        $this->artisan('documents:reprocess', ['--id' => $doc->id])
            ->assertExitCode(0);
            
        $this->assertEquals('pending', $doc->fresh()->status);
    }

    /**
     * Regression for M14: --all must skip 'pending' documents by default
     * to avoid re-queuing jobs that are already enqueued.
     */
    public function test_reprocess_all_skips_pending_documents_by_default(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $ready = Document::create([
            'user_id' => $user->id, 'filename' => 'ready.pdf',
            'original_name' => 'ready.pdf', 'file_path' => 'path/ready.pdf',
            'mime_type' => 'application/pdf', 'file_size_bytes' => 100, 'status' => 'ready',
        ]);
        $error = Document::create([
            'user_id' => $user->id, 'filename' => 'error.pdf',
            'original_name' => 'error.pdf', 'file_path' => 'path/error.pdf',
            'mime_type' => 'application/pdf', 'file_size_bytes' => 100, 'status' => 'error',
        ]);
        $pending = Document::create([
            'user_id' => $user->id, 'filename' => 'pending.pdf',
            'original_name' => 'pending.pdf', 'file_path' => 'path/pending.pdf',
            'mime_type' => 'application/pdf', 'file_size_bytes' => 100, 'status' => 'pending',
        ]);

        $this->artisan('documents:reprocess', ['--all' => true])->assertExitCode(0);

        // ready and error must be dispatched; pending must NOT be re-dispatched.
        Queue::assertPushed(ProcessDocument::class, fn ($j) => $j->document->id === $ready->id);
        Queue::assertPushed(ProcessDocument::class, fn ($j) => $j->document->id === $error->id);
        Queue::assertNotPushed(ProcessDocument::class, fn ($j) => $j->document->id === $pending->id);
    }

    /**
     * Regression for M14: --include-pending must also dispatch pending documents
     * when combined with --all.
     */
    public function test_reprocess_all_include_pending_dispatches_pending_documents(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $pending = Document::create([
            'user_id' => $user->id, 'filename' => 'pend2.pdf',
            'original_name' => 'pend2.pdf', 'file_path' => 'path/pend2.pdf',
            'mime_type' => 'application/pdf', 'file_size_bytes' => 100, 'status' => 'pending',
        ]);

        $this->artisan('documents:reprocess', ['--all' => true, '--include-pending' => true])
            ->assertExitCode(0);

        Queue::assertPushed(ProcessDocument::class, fn ($j) => $j->document->id === $pending->id);
    }
}
