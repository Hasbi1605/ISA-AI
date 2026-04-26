<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AIRuntimeService;
use App\Services\Document\IngestThrottleService;
use App\Services\Document\Parsing\DocumentParserFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];
    
    public $timeout = 900;

    public function __construct(public Document $document)
    {
    }

    public function handle(AIRuntimeService $AIRuntimeService): void
    {
        try {
            $this->document->update(['status' => 'processing']);

            $filePath = $this->resolveFilePath();
            
            if (!file_exists($filePath)) {
                throw new Exception("File not found. Tried: {$this->document->file_path} and private/{$this->document->file_path}");
            }

            $laravelResult = $this->processWithLaravel($filePath);
            
            if ($laravelResult['status'] === 'success') {
                $this->document->update([
                    'status' => 'ready',
                    'provider_file_id' => $laravelResult['provider_file_id'] ?? null,
                ]);
                return;
            }

            Log::warning('ProcessDocument: Laravel processing failed, falling back to Python', [
                'document_id' => $this->document->id,
                'error' => $laravelResult['message'] ?? 'Unknown',
            ]);

            $result = $AIRuntimeService->documentProcess(
                $filePath,
                $this->document->original_name,
                $this->document->user_id
            );

            if (($result['status'] ?? 'error') === 'success') {
                $this->document->update([
                    'status' => 'ready',
                    'provider_file_id' => $result['provider_file_id'] ?? null,
                ]);
            } else {
                throw new Exception("Process failed: " . ($result['message'] ?? 'Unknown error'));
            }

        } catch (Exception $e) {
            $this->document->update(['status' => 'error']);
            logger()->error("Document processing failed for ID {$this->document->id}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function resolveFilePath(): string
    {
        $filePath = Storage::disk('local')->path($this->document->file_path);
        
        if (!file_exists($filePath)) {
            $filePath = Storage::disk('local')->path('private/' . $this->document->file_path);
        }
        
        return $filePath;
    }

    protected function processWithLaravel(string $filePath): array
    {
        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            $supportedExtensions = ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'csv'];
            
            if (!in_array($extension, $supportedExtensions)) {
                return [
                    'status' => 'skip',
                    'message' => "File format {$extension} not supported for Laravel processing",
                ];
            }

            $factory = new DocumentParserFactory();
            
            if (!$factory->supports($filePath)) {
                return [
                    'status' => 'skip',
                    'message' => "No parser available for file format: {$extension}",
                ];
            }

            $pages = $factory->parse($filePath);
            
            if (empty($pages)) {
                return [
                    'status' => 'error',
                    'message' => 'Document parsing produced no content',
                ];
            }

            Log::info('ProcessDocument: Laravel parsed document', [
                'document_id' => $this->document->id,
                'pages' => count($pages),
            ]);

            return [
                'status' => 'success',
                'provider_file_id' => null,
                'pages' => count($pages),
            ];

        } catch (\Throwable $e) {
            Log::error('ProcessDocument: Laravel processing error', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->document->update(['status' => 'error']);
        logger()->error("Document processing permanently failed for ID {$this->document->id}: " . $exception->getMessage());
    }
}
