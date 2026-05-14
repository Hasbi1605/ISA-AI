<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessDocument;
use App\Jobs\RenderDocumentPreview;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_updates_status_to_ready_on_success(): void
    {
        Storage::fake('local');
        config()->set('services.ai_document_service.url', 'http://python-ai-docs:8002');
        config()->set('services.ai_document_service.token', 'internal-token');
        $user = User::factory()->create();

        // Create dummy file
        $filePath = 'documents/' . $user->id . '/dummy.pdf';
        Storage::disk('local')->put($filePath, 'dummy content');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'dummy.pdf',
            'original_name' => 'dummy.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        Http::fake([
            '*' => Http::response(['message' => 'success'], 200),
        ]);

        $job = new ProcessDocument($document);
        $job->handle();

        $this->assertEquals('ready', $document->fresh()->status);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://python-ai-docs:8002/api/documents/process'
                && $request->hasHeader('Authorization', 'Bearer internal-token');
        });
    }

    public function test_job_throws_runtime_exception_on_http_failure(): void
    {
        Storage::fake('local');
        config()->set('services.ai_document_service.url', 'http://python-ai-docs:8002');
        $user = User::factory()->create();

        $filePath = 'documents/' . $user->id . '/dummy2.pdf';
        Storage::disk('local')->put($filePath, 'dummy content');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'dummy2.pdf',
            'original_name' => 'dummy2.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        Http::fake([
            '*' => Http::response(['message' => 'error'], 500),
        ]);

        $job = new ProcessDocument($document);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Microservice error:');
        $job->handle();
    }

    public function test_failed_method_sets_document_status_to_error(): void
    {
        $user = User::factory()->create();

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'failed-callback.pdf',
            'original_name' => 'failed-callback.pdf',
            'file_path' => 'documents/' . $user->id . '/failed-callback.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'processing',
        ]);

        $job = new ProcessDocument($document);
        $job->failed(new \RuntimeException('permanent failure'));

        $this->assertSame('error', $document->fresh()->status);
    }

    public function test_job_updates_status_to_error_if_file_missing(): void
    {
        $user = User::factory()->create();

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'missing.pdf',
            'original_name' => 'missing.pdf',
            'file_path' => 'documents/' . $user->id . '/missing.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        // Do not put file in storage

        $job = new ProcessDocument($document);
        $job->handle();

        $this->assertEquals('error', $document->fresh()->status);
    }

    public function test_job_does_not_dispatch_render_preview_when_document_was_deleted_mid_flight(): void
    {
        Storage::fake('local');
        config()->set('services.ai_document_service.url', 'http://python-ai-docs:8002');
        Queue::fake();

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/race.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 fake');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'race.pdf',
            'original_name' => 'race.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        Http::fake(['*' => Http::response(['message' => 'success'], 200)]);

        $job = new ProcessDocument($document);

        $document->forceDelete();

        $job->handle();

        Queue::assertNotPushed(RenderDocumentPreview::class);
    }

    public function test_job_keeps_status_ready_when_preview_dispatch_throws(): void
    {
        Storage::fake('local');
        config()->set('services.ai_document_service.url', 'http://python-ai-docs:8002');

        // Force the dispatcher to throw to simulate a queue connection failure.
        \Illuminate\Support\Facades\Queue::shouldReceive('connection')
            ->andThrow(new \RuntimeException('queue down'));

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/dispatch-fail.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 fake');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'dispatch-fail.pdf',
            'original_name' => 'dispatch-fail.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        Http::fake(['*' => Http::response(['message' => 'success'], 200)]);

        (new ProcessDocument($document))->handle();

        // Document was successfully processed; preview dispatch failure must NOT
        // revert status back to 'error'.
        $this->assertSame('ready', $document->fresh()->status);
    }

    public function test_job_dispatches_render_preview_after_successful_processing(): void
    {
        Storage::fake('local');
        config()->set('services.ai_document_service.url', 'http://python-ai-docs:8002');
        Queue::fake();

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/dispatch.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 fake');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'dispatch.pdf',
            'original_name' => 'dispatch.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        Http::fake(['*' => Http::response(['message' => 'success'], 200)]);

        (new ProcessDocument($document))->handle();

        Queue::assertPushed(RenderDocumentPreview::class, function ($job) use ($document) {
            return $job->document->id === $document->id;
        });
    }

    public function test_job_skips_silently_when_document_is_deleted_before_processing(): void
    {
        Storage::fake('local');
        Http::fake();

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/race.pdf';
        Storage::disk('local')->put($filePath, 'dummy content');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'race.pdf',
            'original_name' => 'race.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        $documentId = $document->id;

        // Simulate user deleting the document before worker starts.
        // Document now uses hard delete (issue #159), so delete() removes the row.
        $document->delete();

        $job = new ProcessDocument($document);
        $job->handle();

        // Row is gone — job should skip silently, no error written, no HTTP call
        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        Http::assertNothingSent();
    }

    public function test_job_has_delete_when_missing_models_set_to_true(): void
    {
        // When a document is hard-deleted before the worker picks up the job,
        // Laravel's SerializesModels will fail to restore the model.
        // $deleteWhenMissingModels = true tells the queue to silently discard
        // the job instead of throwing a ModelNotFoundException.
        $user = User::factory()->create();
        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'missing.pdf',
            'original_name' => 'missing.pdf',
            'file_path' => 'documents/'.$user->id.'/missing.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        $job = new ProcessDocument($document);

        $this->assertTrue($job->deleteWhenMissingModels);
    }

    /**
     * Regression for H3: document deleted between HTTP success and status update.
     * The job must attempt vector cleanup and NOT mark the (now-missing) row ready.
     */
    public function test_job_cleans_up_vectors_when_document_deleted_after_http_completes(): void
    {
        Storage::fake('local');
        config()->set('services.ai_document_service.url', 'http://python-ai-docs:8002');
        config()->set('services.ai_document_service.token', 'internal-token');
        Queue::fake();

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/deleted-mid.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 fake');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'deleted-mid.pdf',
            'original_name' => 'deleted-mid.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        $documentId = $document->id;

        // Simulate the document being hard-deleted while the Python ingest HTTP call
        // is in flight. The fake callback deletes the row so that the post-HTTP
        // fresh-check in handle() returns null.
        Http::fake([
            '*/api/documents/process' => function () use ($document) {
                $document->forceDelete();

                return Http::response(['message' => 'success'], 200);
            },
            '*/api/documents/*' => Http::response(['message' => 'deleted'], 200),
        ]);

        (new ProcessDocument($document))->handle();

        // Row must still be absent (was hard-deleted during the fake HTTP call).
        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        // Status must NOT have been set to 'ready' on a deleted row.
        // Vector-cleanup HTTP call should have been attempted.
        Http::assertSent(fn ($req) => str_contains($req->url(), 'deleted-mid.pdf'));
        // Preview job must NOT have been dispatched.
        Queue::assertNotPushed(RenderDocumentPreview::class);
    }

    // -------------------------------------------------------------------------
    // Stale-job guard: ProcessDocument
    // -------------------------------------------------------------------------

    public function test_job_is_skipped_when_document_is_already_processing(): void
    {
        Storage::fake('local');
        Http::fake();

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/already-processing.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 fake');

        // Document is already in 'processing' state — another worker grabbed it first.
        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'already-processing.pdf',
            'original_name' => 'already-processing.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'processing',
        ]);

        (new ProcessDocument($document))->handle();

        // Status must remain 'processing' — the stale job must not reset it to ready.
        $this->assertSame('processing', $document->fresh()->status);
        // No HTTP call should have been made for the duplicate job.
        Http::assertNothingSent();
    }

    public function test_job_is_skipped_when_document_is_already_ready(): void
    {
        Storage::fake('local');
        Http::fake();

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/already-ready.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 fake');

        // Document already successfully processed by another job.
        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'already-ready.pdf',
            'original_name' => 'already-ready.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'ready',
        ]);

        (new ProcessDocument($document))->handle();

        $this->assertSame('ready', $document->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_stale_job_does_not_set_ready_when_document_reprocessed_mid_flight(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('services.ai_document_service.url', 'http://python-ai-docs:8002');
        config()->set('services.ai_document_service.token', 'internal-token');

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/reprocessed.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 fake');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'reprocessed.pdf',
            'original_name' => 'reprocessed.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'pending',
        ]);

        // While this job was running (HTTP call in flight), a new reprocess
        // was triggered which reset the document status back to 'pending'
        // (simulated by modifying status inside the HTTP fake callback).
        Http::fake([
            '*/api/documents/process' => function () use ($document) {
                // Simulate another worker or admin resetting the document to pending.
                Document::where('id', $document->id)->update(['status' => 'pending']);

                return Http::response(['message' => 'success'], 200);
            },
            '*/api/documents/*' => Http::response(['message' => 'deleted'], 200),
        ]);

        (new ProcessDocument($document))->handle();

        // The post-success guard detected the status change and did NOT set 'ready'.
        // The document should remain in 'pending' so the newer job can claim it.
        $this->assertSame('pending', $document->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Stale-job guard: RenderDocumentPreview
    // -------------------------------------------------------------------------

    public function test_render_preview_job_skips_document_whose_preview_is_already_ready(): void
    {
        $user = User::factory()->create();

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'preview-ready.pdf',
            'original_name' => 'preview-ready.pdf',
            'file_path' => 'documents/'.$user->id.'/preview-ready.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_READY,
            'preview_html_path' => 'previews/preview-ready.html',
        ]);

        $rendererCalled = false;
        $renderer = new class($rendererCalled) extends \App\Services\Documents\DocumentPreviewRenderer
        {
            public function __construct(private bool &$called) {}

            public function render(\App\Models\Document $document): void
            {
                $this->called = true;
            }
        };

        $job = new \App\Jobs\RenderDocumentPreview($document);
        $job->handle($renderer);

        $this->assertFalse($rendererCalled, 'Renderer must not be called when preview is already ready.');
    }
}
