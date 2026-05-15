<?php

namespace Tests\Feature\Chat;

use App\Http\Controllers\Chat\ChatStreamController;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use App\Services\AIService;
use App\Services\ChatOrchestrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatStreamTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Auth / ownership guards
    // -------------------------------------------------------------------------

    public function test_stream_returns_401_for_unauthenticated_user(): void
    {
        $owner = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Test conversation',
        ]);

        $this->get(route('chat.stream', ['conversationId' => $conversation->id]))
            ->assertRedirect(route('login'));
    }

    public function test_stream_returns_404_for_foreign_conversation(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Owned by owner',
        ]);

        $this->actingAs($otherUser)
            ->get(route('chat.stream', ['conversationId' => $conversation->id]))
            ->assertNotFound();
    }

    public function test_stream_returns_404_for_nonexistent_conversation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('chat.stream', ['conversationId' => 999999]))
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // SSE output / header tests
    // -------------------------------------------------------------------------

    public function test_stream_returns_sse_content_type_header(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Header test',
        ]);

        $this->app->bind(AIService::class, fn () => new class extends AIService
        {
            public function sendChat(
                array $messages,
                ?array $document_filenames = null,
                ?string $user_id = null,
                bool $force_web_search = false,
                ?string $source_policy = null,
                bool $allow_auto_realtime_web = true,
                ?array $document_ids = null,
            ): \Generator {
                yield 'OK';
            }
        });

        $history = json_encode([['role' => 'user', 'content' => 'Halo']]);

        $response = $this->actingAs($user)
            ->get(route('chat.stream', ['conversationId' => $conversation->id]).'?history='.urlencode($history));

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_stream_sends_sse_chunks_for_valid_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Streaming test',
        ]);

        $this->app->bind(AIService::class, fn () => new class extends AIService
        {
            public function sendChat(
                array $messages,
                ?array $document_filenames = null,
                ?string $user_id = null,
                bool $force_web_search = false,
                ?string $source_policy = null,
                bool $allow_auto_realtime_web = true,
                ?array $document_ids = null,
            ): \Generator {
                yield 'Halo ';
                yield 'dunia!';
            }
        });

        $body = $this->runExecuteStream($user, $conversation, [
            ['role' => 'user', 'content' => 'Halo'],
        ]);

        $this->assertStringContainsString('event: chunk', $body);
        $this->assertStringContainsString('Halo ', $body);
        $this->assertStringContainsString('dunia!', $body);
        $this->assertStringContainsString('event: done', $body);
    }

    // -------------------------------------------------------------------------
    // DB persistence
    // -------------------------------------------------------------------------

    public function test_stream_persists_assistant_message_to_db_after_stream(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Persistence test',
        ]);

        $this->app->bind(AIService::class, fn () => new class extends AIService
        {
            public function sendChat(
                array $messages,
                ?array $document_filenames = null,
                ?string $user_id = null,
                bool $force_web_search = false,
                ?string $source_policy = null,
                bool $allow_auto_realtime_web = true,
                ?array $document_ids = null,
            ): \Generator {
                yield 'Jawaban dari streaming.';
            }
        });

        $this->runExecuteStream($user, $conversation, [
            ['role' => 'user', 'content' => 'Pertanyaan'],
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari streaming.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Idempotency
    // -------------------------------------------------------------------------

    public function test_stream_does_not_duplicate_message_if_assistant_already_exists(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Idempotency test',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Pertanyaan',
        ]);

        // Simulate job already saved the assistant message
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari job.',
        ]);

        $this->app->bind(AIService::class, fn () => new class extends AIService
        {
            public function sendChat(
                array $messages,
                ?array $document_filenames = null,
                ?string $user_id = null,
                bool $force_web_search = false,
                ?string $source_policy = null,
                bool $allow_auto_realtime_web = true,
                ?array $document_ids = null,
            ): \Generator {
                yield 'Jawaban duplikat dari stream.';
            }
        });

        $this->runExecuteStream($user, $conversation, [
            ['role' => 'user', 'content' => 'Pertanyaan'],
        ]);

        // Only one assistant message should exist
        $assistantCount = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->count();

        $this->assertSame(1, $assistantCount, 'Tidak boleh ada duplikat assistant message');
    }

    // -------------------------------------------------------------------------
    // Error sentinel
    // -------------------------------------------------------------------------

    public function test_stream_saves_error_message_when_ai_returns_sentinel(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Error sentinel test',
        ]);

        $this->app->bind(AIService::class, fn () => new class extends AIService
        {
            public function sendChat(
                array $messages,
                ?array $document_filenames = null,
                ?string $user_id = null,
                bool $force_web_search = false,
                ?string $source_policy = null,
                bool $allow_auto_realtime_web = true,
                ?array $document_ids = null,
            ): \Generator {
                yield AIService::ERROR_SENTINEL.'❌ Kesalahan sistem.';
            }
        });

        $body = $this->runExecuteStream($user, $conversation, [
            ['role' => 'user', 'content' => 'Pertanyaan'],
        ]);

        $this->assertStringContainsString('event: error', $body);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'is_error' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Document filtering
    // -------------------------------------------------------------------------

    public function test_stream_only_uses_owned_ready_documents(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Document filter test',
        ]);

        $readyDoc = Document::create([
            'user_id' => $user->id,
            'filename' => 'ready.pdf',
            'original_name' => 'ready.pdf',
            'file_path' => 'documents/'.$user->id.'/ready.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 100,
            'status' => 'ready',
        ]);

        $foreignDoc = Document::create([
            'user_id' => $otherUser->id,
            'filename' => 'foreign.pdf',
            'original_name' => 'foreign.pdf',
            'file_path' => 'documents/'.$otherUser->id.'/foreign.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 100,
            'status' => 'ready',
        ]);

        $captured = new \stdClass;
        $captured->documentIds = null;
        $captured->filenames = null;

        $this->app->bind(AIService::class, function () use ($captured) {
            return new class($captured) extends AIService
            {
                public function __construct(private \stdClass $captured)
                {
                    parent::__construct();
                }

                public function sendChat(
                    array $messages,
                    ?array $document_filenames = null,
                    ?string $user_id = null,
                    bool $force_web_search = false,
                    ?string $source_policy = null,
                    bool $allow_auto_realtime_web = true,
                    ?array $document_ids = null,
                ): \Generator {
                    $this->captured->documentIds = $document_ids;
                    $this->captured->filenames = $document_filenames;
                    yield 'OK';
                }
            };
        });

        $this->actingAs($user);

        $orchestrator = app(ChatOrchestrationService::class);
        $docContext = $orchestrator->getActiveDocumentContext([$readyDoc->id, $foreignDoc->id]);

        $controller = app(ChatStreamController::class);
        $controller->executeStream(
            app(AIService::class),
            $orchestrator,
            [['role' => 'user', 'content' => 'Halo']],
            $docContext['filenames'],
            $docContext['ids'],
            $orchestrator->getSourcePolicy($docContext['filenames']),
            $orchestrator->shouldAllowAutoRealtimeWeb($docContext['filenames']),
            false,
            $conversation->id,
            $conversation,
            $user,
        );

        $this->assertNotNull($captured->documentIds);
        $this->assertContains($readyDoc->id, $captured->documentIds);
        $this->assertNotContains($foreignDoc->id, $captured->documentIds);
        $this->assertSame(['ready.pdf'], $captured->filenames);
    }

    // -------------------------------------------------------------------------
    // message-id event
    // -------------------------------------------------------------------------

    public function test_stream_sends_message_id_event_after_persistence(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Message ID event test',
        ]);

        $this->app->bind(AIService::class, fn () => new class extends AIService
        {
            public function sendChat(
                array $messages,
                ?array $document_filenames = null,
                ?string $user_id = null,
                bool $force_web_search = false,
                ?string $source_policy = null,
                bool $allow_auto_realtime_web = true,
                ?array $document_ids = null,
            ): \Generator {
                yield 'Jawaban tersimpan.';
            }
        });

        $body = $this->runExecuteStream($user, $conversation, [
            ['role' => 'user', 'content' => 'Pertanyaan'],
        ]);

        $this->assertStringContainsString('event: message-id', $body);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Call executeStream() directly and capture its echo output.
     * This avoids the PHPUnit "risky" warning caused by StreamedResponse closures.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<int, int>  $documentIds
     */
    private function runExecuteStream(
        User $user,
        Conversation $conversation,
        array $history,
        array $documentIds = [],
        bool $webSearchMode = false,
    ): string {
        $this->actingAs($user);

        $orchestrator = app(ChatOrchestrationService::class);
        $docContext = $orchestrator->getActiveDocumentContext($documentIds);

        $controller = app(ChatStreamController::class);

        ob_start();
        $controller->executeStream(
            app(AIService::class),
            $orchestrator,
            $history,
            $docContext['filenames'],
            $docContext['ids'],
            $orchestrator->getSourcePolicy($docContext['filenames']),
            $orchestrator->shouldAllowAutoRealtimeWeb($docContext['filenames']),
            $webSearchMode,
            $conversation->id,
            $conversation,
            $user,
        );

        return (string) ob_get_clean();
    }
}
