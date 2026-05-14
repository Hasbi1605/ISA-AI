<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 900; // 15 minutes timeout

    /**
     * If the Document model is hard-deleted before the worker picks up the
     * job, Laravel will fail to unserialize it. Setting this to true tells
     * the queue to silently discard the job instead of throwing an exception.
     *
     * Soft-deleted documents are handled inside handle() via fresh()->trashed().
     *
     * @var bool
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public Document $document)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Fresh-check the document to detect race with user deletion.
        // If the document was hard-deleted before the worker started,
        // skip silently instead of logging a spurious "file not found" error.
        // Note: Document no longer uses SoftDeletes (issue #159), so we only
        // need to check for null (hard-deleted). The $deleteWhenMissingModels
        // property handles the case where the model is missing during
        // unserialization.
        $fresh = $this->document->fresh();

        if ($fresh === null) {
            logger()->info("ProcessDocument skipped: document {$this->document->id} was deleted before processing started.");

            return;
        }

        $this->document = $fresh;

        // 1. Update status to processing
        $this->document->update(['status' => 'processing']);

            // 2. Prepare file - Try both private and public paths
            $filePath = Storage::disk('local')->path($this->document->file_path);

            // Laravel 11+ stores in private/ by default
            if (! file_exists($filePath)) {
                $filePath = Storage::disk('local')->path('private/'.$this->document->file_path);
            }

        if (! file_exists($filePath)) {
            $this->document->update(['status' => 'error']);
            logger()->error("Document processing failed for ID {$this->document->id}: File not found. Tried: {$this->document->file_path} and private/{$this->document->file_path}");

            return;
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            $this->document->update(['status' => 'error']);
            logger()->error("Document processing failed for ID {$this->document->id}: unable to read file");

            return;
        }

            // 3. Send to Python Microservice (with extended timeout for embedding)
            $pythonUrl = rtrim((string) config('services.ai_document_service.url', config('services.ai_service.url', 'http://127.0.0.1:8001')), '/')
                .'/api/documents/process';
            $token = config('services.ai_document_service.token', config('services.ai_service.token'));

            $response = Http::timeout(900) // 15 minutes timeout for large documents
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                ])
                ->attach(
                    'file',
                    $fileContent,
                    $this->document->original_name
                )
                ->post($pythonUrl, [
                    'user_id' => (string) $this->document->user_id,
                ]);

        if ($response->successful()) {
            $freshDocument = $this->document->fresh();
            if ($freshDocument === null) {
                try {
                    $this->deleteVectorsForDocument($this->document);
                } catch (\Throwable $ignored) {
                }

                return;
            }

            // 4. Update status to ready
            $freshDocument->update(['status' => 'ready']);
            $this->document = $freshDocument;

                // 5. Dispatch preview rendering job as a fallback if the
                // eager upload-time dispatch has not already completed it.
            try {
                $freshDocument = $this->document->fresh();
                if ($freshDocument !== null && $freshDocument->preview_status !== Document::PREVIEW_STATUS_READY) {
                    RenderDocumentPreview::dispatch($freshDocument);
                }
            } catch (\Throwable $e) {
                logger()->warning("Preview dispatch failed for document {$this->document->id}, proceeding: ".$e->getMessage());
            }
        } else {
            throw new \RuntimeException('Microservice error: '.$response->body());
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->document->update(['status' => 'error']);
        logger()->error("Document processing permanently failed for ID {$this->document->id}: ".$exception->getMessage());
    }

    private function deleteVectorsForDocument(Document $doc): void
    {
        $pythonUrl = rtrim((string) config('services.ai_document_service.url', config('services.ai_service.url', 'http://127.0.0.1:8001')), '/')
            .'/api/documents/'.urlencode($doc->original_name)
            .'?'.http_build_query(['user_id' => (string) $doc->user_id]);
        $token = config('services.ai_document_service.token', config('services.ai_service.token'));

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->delete($pythonUrl);

        if (! $response->successful() && $response->status() !== 404) {
            throw new \RuntimeException('Vector deletion failed: '.$response->body());
        }
    }
}
