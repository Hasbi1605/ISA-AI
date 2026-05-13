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

    public function test_job_skips_silently_when_document_is_soft_deleted_before_processing(): void
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

        // Simulate user soft-deleting the document before worker starts
        $document->delete();

        $job = new ProcessDocument($document);
        $job->handle();

        // Status should NOT be changed to 'error' — row is trashed, skip silently
        $this->assertSoftDeleted($document);
        $this->assertSame('pending', Document::withTrashed()->find($document->id)->status);

        // Python service should not be called
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
}
