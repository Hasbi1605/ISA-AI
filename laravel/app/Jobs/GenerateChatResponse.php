<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AIService;
use App\Services\ChatOrchestrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GenerateChatResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<int, int|string>  $conversationDocuments
     */
    public function __construct(
        public readonly int $conversationId,
        public readonly int $userId,
        public readonly array $history,
        public readonly array $conversationDocuments = [],
        public readonly bool $webSearchMode = false,
    ) {
        $this->onQueue('default');
    }

    public function handle(AIService $aiService, ChatOrchestrationService $orchestrator): void
    {
        Auth::loginUsingId($this->userId);
        set_time_limit($this->timeout);

        if (! $this->conversationStillExists()) {
            return;
        }

        $documentFilenames = $orchestrator->getDocumentFilenames($this->conversationDocuments);
        $sourcePolicy = $orchestrator->getSourcePolicy($documentFilenames);
        $allowAutoRealtimeWeb = $orchestrator->shouldAllowAutoRealtimeWeb($documentFilenames);

        $fullResponse = '';
        $streamBuffer = '';
        $sources = [];

        foreach (
            $aiService->sendChat(
                $this->history,
                $documentFilenames,
                (string) $this->userId,
                $this->webSearchMode,
                $sourcePolicy,
                $allowAutoRealtimeWeb
            ) as $chunk
        ) {
            [$chunk, $streamBuffer, $_modelName, $parsedSources] = $orchestrator->extractStreamMetadata(
                (string) $chunk,
                $streamBuffer
            );

            if (! empty($parsedSources)) {
                $sources = $parsedSources;
            }

            $chunk = $orchestrator->sanitizeAssistantOutput((string) $chunk);

            if ($chunk !== '') {
                $fullResponse .= $chunk;
            }
        }

        // Detect sentinel prefix injected by AIService on network/service errors.
        // Store these as is_error=true so the UI can show a distinct error bubble
        // instead of treating the fallback message as a normal AI answer.
        if (str_starts_with($fullResponse, AIService::ERROR_SENTINEL)) {
            $errorContent = substr($fullResponse, strlen(AIService::ERROR_SENTINEL));
            $errorContent = trim($errorContent) !== '' ? trim($errorContent) : 'Maaf, ISTA AI gagal merespon. Silakan coba lagi.';

            if ($orchestrator->saveErrorMessage($this->conversationId, $errorContent, $this->userId) !== null) {
                Conversation::query()
                    ->whereKey($this->conversationId)
                    ->where('user_id', $this->userId)
                    ->touch();
            }

            return;
        }

        $cleanContent = $orchestrator->cleanResponseContent($fullResponse);

        if (! empty($sources)) {
            $cleanContent .= $orchestrator->sanitizeAndFormatSources($sources);
        }

        if ($cleanContent === '') {
            $cleanContent = 'Maaf, ISTA AI belum menerima jawaban yang bisa ditampilkan. Silakan coba lagi.';
        }

        if ($orchestrator->saveAssistantMessage($this->conversationId, $cleanContent, $this->userId) !== null) {
            Conversation::query()
                ->whereKey($this->conversationId)
                ->where('user_id', $this->userId)
                ->touch();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Auth::loginUsingId($this->userId);

        Log::error('Background chat response failed', [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'message' => $exception?->getMessage(),
        ]);

        if (! $this->conversationStillExists()) {
            return;
        }

        Message::create([
            'conversation_id' => $this->conversationId,
            'role' => 'assistant',
            'content' => 'Maaf, jawaban gagal diproses. Silakan coba kirim ulang.',
            'is_error' => true,
        ]);

        Conversation::query()
            ->whereKey($this->conversationId)
            ->where('user_id', $this->userId)
            ->touch();
    }

    private function conversationStillExists(): bool
    {
        return Conversation::query()
            ->whereKey($this->conversationId)
            ->where('user_id', $this->userId)
            ->exists();
    }
}
