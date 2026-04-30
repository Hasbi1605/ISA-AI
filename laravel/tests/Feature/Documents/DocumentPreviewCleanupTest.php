<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentLifecycleService;
use App\Services\Documents\DocumentPreviewRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPreviewCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_document_removes_preview_html_file(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/sample.docx';
        $previewPath = DocumentPreviewRenderer::PREVIEW_DIR.'/'.$user->id.'/42.html';

        Storage::disk('local')->put($filePath, 'fake docx content');
        Storage::disk('local')->put($previewPath, '<p>preview html</p>');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'sample.docx',
            'original_name' => 'sample.docx',
            'file_path' => $filePath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size_bytes' => 123,
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_READY,
            'preview_html_path' => $previewPath,
        ]);

        app(DocumentLifecycleService::class)->deleteDocument($document);

        $this->assertSoftDeleted($document);
        Storage::disk('local')->assertMissing($filePath);
        Storage::disk('local')->assertMissing($previewPath);
    }

    public function test_delete_document_without_preview_path_is_safe(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create();
        $filePath = 'documents/'.$user->id.'/no-preview.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 fake');

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'no-preview.pdf',
            'original_name' => 'no-preview.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 123,
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_PENDING,
            'preview_html_path' => null,
        ]);

        app(DocumentLifecycleService::class)->deleteDocument($document);

        $this->assertSoftDeleted($document);
    }
}
