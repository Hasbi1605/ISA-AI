<?php

namespace Tests\Unit\Services\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentPreviewRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DocumentPreviewRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_pdf_marks_preview_ready_without_html(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'sample.pdf',
            'original_name' => 'sample.pdf',
            'file_path' => 'documents/'.$user->id.'/sample.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1234,
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_PENDING,
        ]);

        Storage::disk('local')->put($document->file_path, '%PDF-1.4 fake');

        app(DocumentPreviewRenderer::class)->render($document->refresh());

        $document->refresh();
        $this->assertSame(Document::PREVIEW_STATUS_READY, $document->preview_status);
        $this->assertNull($document->preview_html_path);
    }

    public function test_render_docx_writes_html_preview(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $relativePath = 'documents/'.$user->id.'/sample.docx';
        $absolutePath = Storage::disk('local')->path($relativePath);
        @mkdir(dirname($absolutePath), 0777, true);

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Selamat datang di ISTA AI preview test.');
        WordIOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'sample.docx',
            'original_name' => 'sample.docx',
            'file_path' => $relativePath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size_bytes' => filesize($absolutePath),
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_PENDING,
        ]);

        app(DocumentPreviewRenderer::class)->render($document->refresh());

        $document->refresh();
        $this->assertSame(Document::PREVIEW_STATUS_READY, $document->preview_status);
        $this->assertNotNull($document->preview_html_path);
        $this->assertTrue(Storage::disk('local')->exists($document->preview_html_path));

        $html = Storage::disk('local')->get($document->preview_html_path);
        $this->assertStringContainsString('ISTA AI preview test', $html);
        $this->assertStringNotContainsString('<body', $html, 'extractBody should strip the body wrapper');
    }

    public function test_render_xlsx_writes_html_preview(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $relativePath = 'documents/'.$user->id.'/sample.xlsx';
        $absolutePath = Storage::disk('local')->path($relativePath);
        @mkdir(dirname($absolutePath), 0777, true);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Bulan');
        $sheet->setCellValue('B1', 'Total');
        $sheet->setCellValue('A2', 'April');
        $sheet->setCellValue('B2', 1500);
        (new Xlsx($spreadsheet))->save($absolutePath);

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'sample.xlsx',
            'original_name' => 'sample.xlsx',
            'file_path' => $relativePath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size_bytes' => filesize($absolutePath),
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_PENDING,
        ]);

        app(DocumentPreviewRenderer::class)->render($document->refresh());

        $document->refresh();
        $this->assertSame(Document::PREVIEW_STATUS_READY, $document->preview_status);
        $this->assertNotNull($document->preview_html_path);
        $html = Storage::disk('local')->get($document->preview_html_path);
        $this->assertStringContainsString('Bulan', $html);
        $this->assertStringContainsString('1500', $html);
        $this->assertStringContainsString('<style', $html, 'extractBody should preserve writer CSS styles');
    }

    public function test_render_xlsx_includes_all_sheets(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $relativePath = 'documents/'.$user->id.'/multi.xlsx';
        $absolutePath = Storage::disk('local')->path($relativePath);
        @mkdir(dirname($absolutePath), 0777, true);

        $spreadsheet = new Spreadsheet;
        $first = $spreadsheet->getActiveSheet();
        $first->setTitle('Pendapatan');
        $first->setCellValue('A1', 'PENDAPATAN_SHEET_MARKER');

        $second = $spreadsheet->createSheet();
        $second->setTitle('Belanja');
        $second->setCellValue('A1', 'BELANJA_SHEET_MARKER');

        (new Xlsx($spreadsheet))->save($absolutePath);

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'multi.xlsx',
            'original_name' => 'multi.xlsx',
            'file_path' => $relativePath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size_bytes' => filesize($absolutePath),
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_PENDING,
        ]);

        app(DocumentPreviewRenderer::class)->render($document->refresh());

        $document->refresh();
        $html = Storage::disk('local')->get($document->preview_html_path);
        $this->assertStringContainsString('PENDAPATAN_SHEET_MARKER', $html);
        $this->assertStringContainsString('BELANJA_SHEET_MARKER', $html);
    }

    public function test_render_marks_failed_when_source_missing(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $document = Document::create([
            'user_id' => $user->id,
            'filename' => 'missing.docx',
            'original_name' => 'missing.docx',
            'file_path' => 'documents/'.$user->id.'/missing.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size_bytes' => 1,
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_PENDING,
        ]);

        app(DocumentPreviewRenderer::class)->render($document->refresh());

        $this->assertSame(Document::PREVIEW_STATUS_FAILED, $document->refresh()->preview_status);
    }
}
