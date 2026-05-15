<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AIService;
use App\Services\ChatOrchestrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController extends Controller
{
    public function stream(Request $request, int $conversationId): StreamedResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Validate conversation ownership
        $conversation = Conversation::query()
            ->whereKey($conversationId)
            ->where('user_id', $user->id)
            ->first();

        if ($conversation === null) {
            abort(404);
        }

        // Parse request parameters
        $history = $this->parseHistory($request->input('history', '[]'));
        $documentIds = $this->parseDocumentIds($request->input('document_ids', '[]'));
        $webSearchMode = filter_var($request->input('web_search_mode', false), FILTER_VALIDATE_BOOLEAN);

        $aiService = app(AIService::class);
        $orchestrator = app(ChatOrchestrationService::class);

        // Resolve document context (owned + ready only) — must run before closure
        // so Auth::id() is still set in the request context.
        $docContext = $orchestrator->getActiveDocumentContext($documentIds);
        $documentFilenames = $docContext['filenames'];
        $resolvedDocumentIds = $docContext['ids'];
        $sourcePolicy = $orchestrator->getSourcePolicy($documentFilenames);
        $allowAutoRealtimeWeb = $orchestrator->shouldAllowAutoRealtimeWeb($documentFilenames);

        return new StreamedResponse(function () use (
            $aiService,
            $orchestrator,
            $history,
            $documentFilenames,
            $resolvedDocumentIds,
            $sourcePolicy,
            $allowAutoRealtimeWeb,
            $webSearchMode,
            $conversationId,
            $conversation,
            $user,
        ) {
            // Disable output buffering so chunks reach the browser immediately
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', false);
            set_time_limit(180);

            if (ob_get_level() > 0) {
                ob_end_flush();
            }

            $this->executeStream(
                $aiService,
                $orchestrator,
                $history,
                $documentFilenames,
                $resolvedDocumentIds,
                $sourcePolicy,
                $allowAutoRealtimeWeb,
                $webSearchMode,
                $conversationId,
                $conversation,
                $user,
            );
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Core streaming logic — extracted so it can be called directly in tests
     * without needing to execute a StreamedResponse closure.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Conversation  $conversation
     */
    public function executeStream(
        AIService $aiService,
        ChatOrchestrationService $orchestrator,
        array $history,
        ?array $documentFilenames,
        array $resolvedDocumentIds,
        string $sourcePolicy,
        bool $allowAutoRealtimeWeb,
        bool $webSearchMode,
        int $conversationId,
        Conversation $conversation,
        \App\Models\User $user,
    ): void {
        $fullResponse = '';
        $streamBuffer = '';
        $sources = [];

        try {
            foreach (
                $aiService->sendChat(
                    $history,
                    $documentFilenames,
                    (string) $user->id,
                    $webSearchMode,
                    $sourcePolicy,
                    $allowAutoRealtimeWeb,
                    $resolvedDocumentIds,
                ) as $rawChunk
            ) {
                // Abort if browser disconnected
                if (connection_aborted()) {
                    return;
                }

                [$chunk, $streamBuffer, $parsedModelName, $parsedSources] = $orchestrator->extractStreamMetadata(
                    (string) $rawChunk,
                    $streamBuffer
                );

                if ($parsedModelName !== null) {
                    $this->sendSseEvent('model-name', $parsedModelName);
                }

                if (! empty($parsedSources)) {
                    $sources = $parsedSources;
                    $this->sendSseEvent('sources', json_encode($sources));
                }

                $chunk = $orchestrator->sanitizeAssistantOutput((string) $chunk);

                if ($chunk !== '') {
                    $fullResponse .= $chunk;
                    $this->sendSseEvent('chunk', $chunk);
                }
            }
        } catch (\Throwable $e) {
            Log::error('ChatStreamController: stream error', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            $this->sendSseEvent('error', 'Maaf, terjadi kesalahan saat streaming jawaban.');
            $this->sendSseEvent('done', '1');

            return;
        }

        // Detect error sentinel from AIService
        if (str_starts_with($fullResponse, AIService::ERROR_SENTINEL)) {
            $errorContent = substr($fullResponse, strlen(AIService::ERROR_SENTINEL));
            $errorContent = trim($errorContent) !== '' ? trim($errorContent) : 'Maaf, ISTA AI gagal merespon. Silakan coba lagi.';

            $orchestrator->saveErrorMessage($conversationId, $errorContent, $user->id);
            $conversation->touch();

            $this->sendSseEvent('error', $errorContent);
            $this->sendSseEvent('done', '1');

            return;
        }

        // Build final content with sources
        $cleanContent = $orchestrator->cleanResponseContent($fullResponse);

        if (! empty($sources)) {
            $cleanContent .= $orchestrator->sanitizeAndFormatSources($sources);
        }

        if ($cleanContent === '') {
            $cleanContent = 'Maaf, ISTA AI belum menerima jawaban yang bisa ditampilkan. Silakan coba lagi.';
        }

        // Persist final message — idempotent: only save if no assistant message
        // exists after the latest user message (prevents duplicate on job+stream race)
        if (! $this->assistantMessageAlreadyExists($conversationId)) {
            $saved = $orchestrator->saveAssistantMessage($conversationId, $cleanContent, $user->id);
            if ($saved !== null) {
                $conversation->touch();
                $this->sendSseEvent('message-id', (string) $saved->id);
            }
        }

        $this->sendSseEvent('done', '1');
    }

    /**
     * Send a single SSE event to the browser.
     */
    private function sendSseEvent(string $event, string $data): void
    {
        // Escape newlines in data so SSE framing is not broken
        $escaped = str_replace(["\r\n", "\r", "\n"], '\\n', $data);
        echo "event: {$event}\n";
        echo "data: {$escaped}\n\n";
        flush();
    }

    /**
     * Check whether an assistant message already exists after the latest user
     * message in this conversation. Used to prevent duplicate persistence when
     * both the SSE stream and the background job complete around the same time.
     */
    private function assistantMessageAlreadyExists(int $conversationId): bool
    {
        $latestUserMessage = Message::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->latest('id')
            ->first();

        if ($latestUserMessage === null) {
            return false;
        }

        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->where('id', '>', $latestUserMessage->id)
            ->exists();
    }

    /**
     * Parse JSON history from query string.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function parseHistory(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                return [];
            }

            return array_values(array_filter(
                array_map(fn ($msg) => is_array($msg) ? [
                    'role' => (string) ($msg['role'] ?? ''),
                    'content' => (string) ($msg['content'] ?? ''),
                ] : null, $decoded),
                fn ($msg) => $msg !== null && $msg['role'] !== '' && $msg['content'] !== '',
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Parse JSON document IDs from query string.
     *
     * @return array<int, int>
     */
    private function parseDocumentIds(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                return [];
            }

            return array_values(array_filter(
                array_map(fn ($id) => is_numeric($id) ? (int) $id : null, $decoded),
                fn ($id) => $id !== null && $id > 0,
            ));
        } catch (\Throwable) {
            return [];
        }
    }
}
