<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Unique claim token for this job dispatch.
     *
     * Generated in the constructor and serialized with the job payload so that
     * all retry attempts carry the SAME token. A newer reprocess job gets a
     * different token, which lets the current job detect that its processing
     * slot has been superseded and bail out gracefully.
     */
    protected string $claimToken;

    /**
     * Cache key under which the current owner's token is stored.
     */
    protected string $claimCacheKey;

    /**
     * Create a new job instance.
     */
    public function __construct(public Document $document)
    {
        $this->claimToken = Str::uuid()->toString();
        $this->claimCacheKey = 'doc_process_claim:'.$document->id;
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

        // ── Stale-job guard: atomic status claim ─────────────────────────────
        // Each job dispatch carries a unique $claimToken (set in the constructor
        // so it survives serialisation for retries). The flow:
        //
        //   1. Try to claim from `pending` via an atomic WHERE update.
        //   2. If that succeeds, register this job's token in cache.
        //   3. If the document is already in `processing`, check whether WE own
        //      that slot (same token still in cache). If we do, it's a retry of
        //      this same job — continue. If a newer job has overwritten the
        //      token, bail out.
        //
        // This prevents a stale job (Job A) from overwriting the progress of a
        // newer job (Job B) that claimed the document after a user-triggered
        // reprocess reset the status to `pending`.
        $claimedFromPending = Document::where('id', $fresh->id)
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if ($claimedFromPending > 0) {
            // Fresh claim: register our token so subsequent steps and failed()
            // can verify ownership. TTL covers all retry attempts plus buffer.
            $ttl = ($this->timeout * $this->tries) + 600;
            Cache::put($this->claimCacheKey, $this->claimToken, $ttl);
        } else {
            // Document is not in `pending`. It could be:
            //   (a) A retry of THIS job — our token is still in cache.
            //   (b) A different, newer job has claimed it — different token.
            //   (c) Cache expired after a long delay — no token in cache.
            //
            // For (a): continue processing (token matches).
            // For (b): bail out — a newer reprocess job has taken ownership.
            // For (c): treat as retry and continue (we can't distinguish from (a)
            //          after cache expiry, but retrying is safer than silently
            //          dropping the attempt).
            $currentToken = Cache::get($this->claimCacheKey);

            if ($currentToken !== null && $currentToken !== $this->claimToken) {
                // A different job has claimed ownership → this job is stale.
                logger()->info('ProcessDocument: skipped — claim superseded by newer job', [
                    'document_id' => $fresh->id,
                    'current_status' => $fresh->refresh()?->status ?? 'deleted',
                ]);

                return;
            }
            // Token matches (or cache expired for a retry) → continue.
        }

            // 2. Prepare file - Try both private and public paths
            $filePath = Storage::disk('local')->path($this->document->file_path);

            // Laravel 11+ stores in private/ by default
            if (! file_exists($filePath)) {
                $filePath = Storage::disk('local')->path('private/'.$this->document->file_path);
            }

        if (! file_exists($filePath)) {
            $this->updateStatusIfClaimOwned('error');
            logger()->error("Document processing failed for ID {$this->document->id}: File not found. Tried: {$this->document->file_path} and private/{$this->document->file_path}");

            return;
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            $this->updateStatusIfClaimOwned('error');
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
                    'document_id' => (string) $this->document->id,
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

            // ── Stale-job guard: post-success status update ───────────────────
            // Verify our claim token is still current BEFORE transitioning to
            // `ready`. If the user triggered a reprocess after our HTTP call
            // started, a new job has claimed the slot and overwritten the token.
            // In that case, this job's ingest result is stale — clean up the
            // vectors we just ingested and exit without setting `ready`.
            // Only skip if a DIFFERENT, non-null token is in cache (active claim
            // by a newer job); proceed if cache is empty or token matches.
            $currentToken = Cache::get($this->claimCacheKey);
            if ($currentToken !== null && $currentToken !== $this->claimToken) {
                logger()->info('ProcessDocument: stale — claim superseded mid-flight; discarding ingested vectors', [
                    'document_id' => $freshDocument->id,
                ]);

                try {
                    $this->deleteVectorsForDocument($freshDocument);
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

    /**
     * Mark the document as `error` only when this job still owns the claim,
     * or when the cache has been cleared (no competing job is active).
     *
     * A final failure from a stale job must not overwrite a newer job's
     * `processing` or `ready` state. Only skip if a DIFFERENT, non-null token
     * is present — that signals an active newer claim.
     */
    public function failed(\Throwable $exception): void
    {
        $currentToken = Cache::get($this->claimCacheKey);

        if ($currentToken !== null && $currentToken !== $this->claimToken) {
            // A newer job is actively processing this document. Bail out.
            logger()->info('ProcessDocument: failed() skipped — claim superseded by newer job', [
                'document_id' => $this->document->id,
            ]);

            return;
        }

        $this->document->update(['status' => 'error']);
        logger()->error("Document processing permanently failed for ID {$this->document->id}: ".$exception->getMessage());
    }

    /**
     * Update the document status only when this job still owns the claim (or
     * when the cache is clear — no active competing job). Skip only if a
     * DIFFERENT, non-null token is present in cache.
     */
    private function updateStatusIfClaimOwned(string $status): void
    {
        $currentToken = Cache::get($this->claimCacheKey);

        if ($currentToken !== null && $currentToken !== $this->claimToken) {
            logger()->info('ProcessDocument: status update skipped — claim superseded', [
                'document_id' => $this->document->id,
                'intended_status' => $status,
            ]);

            return;
        }

        $this->document->update(['status' => $status]);
    }

    private function deleteVectorsForDocument(Document $doc): void
    {
        $pythonUrl = rtrim((string) config('services.ai_document_service.url', config('services.ai_service.url', 'http://127.0.0.1:8001')), '/')
            .'/api/documents/'.urlencode($doc->original_name)
            .'?'.http_build_query([
                'user_id' => (string) $doc->user_id,
                'document_id' => (string) $doc->id,
            ]);
        $token = config('services.ai_document_service.token', config('services.ai_service.token'));

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->delete($pythonUrl);

        if (! $response->successful() && $response->status() !== 404) {
            throw new \RuntimeException('Vector deletion failed: '.$response->body());
        }
    }
}
