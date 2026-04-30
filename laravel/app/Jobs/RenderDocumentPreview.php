<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Documents\DocumentPreviewRenderer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RenderDocumentPreview implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    public function __construct(public Document $document)
    {
        $this->onQueue('document-previews');
    }

    public function uniqueId(): string
    {
        return (string) $this->document->getKey();
    }

    public function handle(DocumentPreviewRenderer $renderer): void
    {
        $fresh = $this->document->fresh();

        if ($fresh === null) {
            return;
        }

        $renderer->render($fresh);
    }

    public function failed(\Throwable $exception): void
    {
        $this->document->forceFill([
            'preview_status' => Document::PREVIEW_STATUS_FAILED,
        ])->save();

        logger()->error('RenderDocumentPreview job permanently failed', [
            'document_id' => $this->document->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
