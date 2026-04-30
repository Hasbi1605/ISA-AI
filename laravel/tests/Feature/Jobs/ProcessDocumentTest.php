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
use Exception;

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

    public function test_job_updates_status_to_error_on_http_failure(): void
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
        $job->handle();

        $this->assertEquals('error', $document->fresh()->status);
        Http::assertSent(fn ($request) => $request->url() === 'http://python-ai-docs:8002/api/documents/process');
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
}
