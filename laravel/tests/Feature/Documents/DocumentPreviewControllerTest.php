<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentPreviewRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_stream_serves_inline_for_owner(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $document = $this->createDocument($user, 'application/pdf', 'sample.pdf');
        Storage::disk('local')->put($document->file_path, '%PDF-1.4 fake');

        $response = $this->actingAs($user)
            ->get(route('documents.preview.stream', $document));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_stream_returns_403_for_other_user(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        $document = $this->createDocument($owner, 'application/pdf', 'sample.pdf');
        Storage::disk('local')->put($document->file_path, '%PDF-1.4 fake');

        $this->actingAs($other)
            ->get(route('documents.preview.stream', $document))
            ->assertForbidden();
    }

    public function test_pdf_stream_returns_404_for_non_pdf_document(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $document = $this->createDocument(
            $user,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'sample.docx'
        );
        Storage::disk('local')->put($document->file_path, 'fake docx');

        $this->actingAs($user)
            ->get(route('documents.preview.stream', $document))
            ->assertNotFound();
    }

    public function test_html_serves_preview_when_ready(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $document = $this->createDocument(
            $user,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'sample.docx'
        );
        Storage::disk('local')->put($document->file_path, 'fake docx');

        $previewPath = DocumentPreviewRenderer::PREVIEW_DIR.'/'.$user->id.'/'.$document->id.'.html';
        Storage::disk('local')->put($previewPath, '<p>Preview content</p>');
        $document->update([
            'preview_html_path' => $previewPath,
            'preview_status' => Document::PREVIEW_STATUS_READY,
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.preview.html', $document));

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Preview content', $response->getContent());
    }

    public function test_html_returns_404_when_preview_not_ready(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $document = $this->createDocument(
            $user,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'sample.docx'
        );
        $document->update(['preview_status' => Document::PREVIEW_STATUS_PENDING]);

        $this->actingAs($user)
            ->get(route('documents.preview.html', $document))
            ->assertNotFound();
    }

    public function test_status_returns_metadata_for_owner(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $document = $this->createDocument($user, 'application/pdf', 'sample.pdf');

        $this->actingAs($user)
            ->get(route('documents.preview.status', $document))
            ->assertOk()
            ->assertJson([
                'id' => $document->id,
                'mime_type' => 'application/pdf',
                'kind' => 'pdf',
                'is_streamable' => true,
                'is_html_preview' => false,
            ]);
    }

    public function test_status_forbidden_for_other_user(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        $document = $this->createDocument($owner, 'application/pdf', 'sample.pdf');

        $this->actingAs($other)
            ->get(route('documents.preview.status', $document))
            ->assertForbidden();
    }

    public function test_preview_routes_require_authentication(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $document = $this->createDocument($user, 'application/pdf', 'sample.pdf');

        $this->get(route('documents.preview.stream', $document))->assertRedirect();
        $this->get(route('documents.preview.html', $document))->assertRedirect();
        $this->get(route('documents.preview.status', $document))->assertRedirect();
    }

    private function createDocument(User $user, string $mime, string $name): Document
    {
        return Document::create([
            'user_id' => $user->id,
            'filename' => $name,
            'original_name' => $name,
            'file_path' => 'documents/'.$user->id.'/'.$name,
            'mime_type' => $mime,
            'file_size_bytes' => 1234,
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_READY,
        ]);
    }
}
