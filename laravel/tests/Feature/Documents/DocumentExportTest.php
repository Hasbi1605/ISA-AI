<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DocumentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_route_streams_download_for_authorized_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $service = Mockery::mock(DocumentExportService::class);
        $service->shouldReceive('exportContent')
            ->once()
            ->with('<p>Isi jawaban</p>', 'pdf', 'jawaban-ai')
            ->andReturn([
                'body' => '%PDF-1.4 fake',
                'content_type' => 'application/pdf',
                'file_name' => 'jawaban-ai.pdf',
            ]);

        $this->app->instance(DocumentExportService::class, $service);

        $response = $this->actingAs($user)->post(route('documents.export'), [
            'content_html' => '<p>Isi jawaban</p>',
            'target_format' => 'pdf',
            'file_name' => 'jawaban-ai',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename="jawaban-ai.pdf"', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 fake', $response->getContent());
    }

    public function test_extract_tables_route_returns_document_tables_for_owner(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $document = $this->createDocument($user, 'application/pdf', 'sample.pdf');

        Storage::disk('local')->put($document->file_path, '%PDF-1.4 fake');

        $service = Mockery::mock(DocumentExportService::class);
        $service->shouldReceive('extractTables')
            ->once()
            ->with(Mockery::on(fn (Document $value) => $value->is($document)))
            ->andReturn([
                'status' => 'success',
                'filename' => 'sample.pdf',
                'tables' => [
                    [
                        'header' => ['Nama', 'Nilai'],
                        'rows' => [['A', '10']],
                    ],
                ],
            ]);

        $this->app->instance(DocumentExportService::class, $service);

        $this->actingAs($user)
            ->get(route('documents.extract-tables', $document))
            ->assertOk()
            ->assertJsonPath('tables.0.header.0', 'Nama')
            ->assertJsonPath('tables.0.rows.0.1', '10');
    }

    public function test_extract_content_route_returns_document_html_for_owner(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $document = $this->createDocument($user, 'application/pdf', 'sample.pdf');

        Storage::disk('local')->put($document->file_path, '%PDF-1.4 fake');

        $service = Mockery::mock(DocumentExportService::class);
        $service->shouldReceive('extractContent')
            ->once()
            ->with(Mockery::on(fn (Document $value) => $value->is($document)))
            ->andReturn([
                'status' => 'success',
                'filename' => 'sample.pdf',
                'content_html' => '<article><p>Isi lengkap dokumen.</p></article>',
            ]);

        $this->app->instance(DocumentExportService::class, $service);

        $this->actingAs($user)
            ->get(route('documents.content-html', $document))
            ->assertOk()
            ->assertJsonPath('content_html', '<article><p>Isi lengkap dokumen.</p></article>');
    }

    public function test_extract_tables_route_forbids_non_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $document = $this->createDocument($owner, 'application/pdf', 'sample.pdf');

        $service = Mockery::mock(DocumentExportService::class);
        $service->shouldNotReceive('extractTables');

        $this->app->instance(DocumentExportService::class, $service);

        $this->actingAs($other)
            ->get(route('documents.extract-tables', $document))
            ->assertForbidden();
    }

    public function test_extract_content_route_forbids_non_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $document = $this->createDocument($owner, 'application/pdf', 'sample.pdf');

        $service = Mockery::mock(DocumentExportService::class);
        $service->shouldNotReceive('extractContent');

        $this->app->instance(DocumentExportService::class, $service);

        $this->actingAs($other)
            ->get(route('documents.content-html', $document))
            ->assertForbidden();
    }

    public function test_export_and_extract_routes_require_authentication(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $document = $this->createDocument($user, 'application/pdf', 'sample.pdf');

        $this->post(route('documents.export'), [
            'content_html' => '<p>Isi jawaban</p>',
            'target_format' => 'pdf',
        ])->assertRedirect();

        $this->get(route('documents.content-html', $document))->assertRedirect();
        $this->get(route('documents.extract-tables', $document))->assertRedirect();
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
