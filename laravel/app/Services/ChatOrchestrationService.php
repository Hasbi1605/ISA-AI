<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Generator;

class ChatOrchestrationService
{
    private const SYSTEM_PROMPT = "Anda adalah ISTA AI, asisten virtual istana pintar. Jawablah dengan sopan dan membantu.";

    private const SANITIZE_REPLACEMENTS = [
        '/\bchunks?\b/i' => 'bagian dokumen',
        '/\bchunk(?:ing|ed)?\b/i' => 'bagian dokumen',
        '/\bembeddings?\b/i' => 'representasi dokumen',
        '/\bvectors?\b/i' => 'indeks dokumen',
        '/\brag\b/i' => 'konteks dokumen',
        '/\bretrieval\b/i' => 'pencarian dokumen',
        '/\btop\s*[- ]?k\b/i' => 'hasil teratas',
    ];

    public function createConversationIfNeeded(?int $currentConversationId, string $prompt): int
    {
        if (!$currentConversationId) {
            $conversation = Conversation::create([
                'user_id' => Auth::id(),
                'title' => substr($prompt, 0, 50) . '...'
            ]);
            return $conversation->id;
        }

        return $currentConversationId;
    }

    public function saveUserMessage(int $conversationId, string $prompt): array
    {
        $userMessage = Message::create([
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $prompt
        ]);

        return $userMessage->toArray();
    }

    public function buildHistory(array $messages): array
    {
        $history = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT]
        ];

        foreach ($messages as $msg) {
            $history[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        return $history;
    }

    public function getDocumentFilenames(array $conversationDocuments): ?array
    {
        if (empty($conversationDocuments)) {
            return null;
        }

        return Document::whereIn('id', $conversationDocuments)
            ->pluck('original_name')
            ->toArray();
    }

    public function getSourcePolicy(?array $documentFilenames): string
    {
        return !empty($documentFilenames) ? 'document_context' : 'hybrid_realtime_auto';
    }

    public function shouldAllowAutoRealtimeWeb(?array $documentFilenames): bool
    {
        return empty($documentFilenames);
    }

    public function sanitizeAndFormatSources(array $sources): string
    {
        if (empty($sources)) {
            return '';
        }

        $markdownSources = "\n\n---\n**Sumber Referensi:**\n";
        $hasValidSource = false;

        foreach ($sources as $source) {
            if (!empty($source['url'])) {
                $title = !empty($source['title']) ? $source['title'] : parse_url($source['url'], PHP_URL_HOST);
                $markdownSources .= "- [🌐 {$title}]({$source['url']})\n  `{$source['url']}`\n";
                $hasValidSource = true;
            } elseif (!empty($source['filename'])) {
                $markdownSources .= "- 📄 {$source['filename']}\n";
                $hasValidSource = true;
            }
        }

        return $hasValidSource ? $markdownSources : '';
    }

    public function sanitizeAssistantOutput(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $sanitized = $text;
        foreach (self::SANITIZE_REPLACEMENTS as $pattern => $replacement) {
            $sanitized = preg_replace($pattern, $replacement, (string) $sanitized);
        }

        return (string) $sanitized;
    }

    public function cleanResponseContent(string $fullResponse): string
    {
        $cleanContent = preg_replace('/\[SOURCES:\[.+?\]\]/s', '', $fullResponse);
        $cleanContent = $this->sanitizeAssistantOutput((string) $cleanContent);
        return trim($cleanContent);
    }

    public function saveAssistantMessage(int $conversationId, string $content): Message
    {
        return Message::create([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $content
        ]);
    }

    public function extractStreamMetadata(string $chunk, string $buffer = ''): array
    {
        $combined = $buffer . $chunk;
        $modelName = null;
        $sources = null;

        if (preg_match('/\[MODEL:(.+?)\]\n?/', $combined, $matches)) {
            $modelName = trim((string) $matches[1]);
            $combined = preg_replace('/\[MODEL:.+?\]\n?/', '', $combined, 1) ?? $combined;
        }

        if (preg_match('/\[SOURCES:(\[.+?\])\]/s', $combined, $matches)) {
            $parsedSources = json_decode($matches[1], true);
            if (is_array($parsedSources)) {
                $sources = $parsedSources;
            }
            $combined = preg_replace('/\[SOURCES:\[.+?\]\]/s', '', $combined, 1) ?? $combined;
        }

        $nextBuffer = '';
        foreach (['[SOURCES:', '[MODEL:'] as $marker) {
            $markerPos = strrpos($combined, $marker);
            if ($markerPos === false) {
                continue;
            }

            $tail = substr($combined, $markerPos);
            $isComplete = $marker === '[SOURCES:'
                ? preg_match('/^\[SOURCES:(\[.+?\])\]/s', $tail) === 1
                : preg_match('/^\[MODEL:(.+?)\]\n?/s', $tail) === 1;

            if (!$isComplete) {
                $nextBuffer = $tail;
                $combined = substr($combined, 0, $markerPos);
                break;
            }
        }

        return [$combined, $nextBuffer, $modelName, $sources];
    }
}