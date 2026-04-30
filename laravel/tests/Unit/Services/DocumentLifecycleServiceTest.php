<?php

namespace Tests\Unit\Services;

use App\Jobs\ProcessDocument;
use App\Models\User;
use App\Services\DocumentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class DocumentLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_document_continues_when_preview_dispatch_fails(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();

        $service = $this->partialMock(DocumentLifecycleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatchPreviewRendering')
                ->once()
                ->andThrow(new \RuntimeException('queue down'));
        });

        $document = $service->uploadDocument(
            UploadedFile::fake()->create('referensi.pdf', 120, 'application/pdf'),
            $user->id,
        );

        $this->assertDatabaseCount('documents', 1);
        $this->assertSame('referensi.pdf', $document->original_name);
        $this->assertSame('pending', $document->status);

        Queue::assertPushed(ProcessDocument::class, 1);
    }
}
