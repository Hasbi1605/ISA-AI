<?php

namespace Tests\Feature\Chat;

use App\Http\Controllers\Chat\ChatStreamController;
use App\Jobs\GenerateChatResponse;
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

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Halo',
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

        $response = $this->actingAs($user)
            ->get(route('chat.stream', ['conversationId' => $conversation->id]));

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

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Halo',
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

        $body = $this->runExecuteStream($user, $conversation);

        $this->assertStringContainsString('event: chunk', $body);
        $this->assertStringContainsString('Halo ', $body);
        $this->assertStringContainsString('dunia!', $body);
        $this->assertStringContainsString('event: done', $body);
    }

    // -------------------------------------------------------------------------
    // History reconstructed from DB
    // -------------------------------------------------------------------------

    public function test_stream_uses_history_from_db_not_query_string(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'History from DB test',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Pertanyaan dari DB',
        ]);

        $capturedMessages = null;

        $this->app->bind(AIService::class, function () use (&$capturedMessages) {
            return new class($capturedMessages) extends AIService
            {
                public function __construct(private mixed &$captured)
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
                    $this->captured = $messages;
                    yield 'OK';
                }
            };
        });

        $this->runExecuteStream($user, $conversation);

        // History harus berisi pesan dari DB
        $this->assertNotNull($capturedMessages);
        $this->assertNotEmpty($capturedMessages);
        $lastMsg = end($capturedMessages);
        $this->assertSame('user', $lastMsg['role']);
        $this->assertSame('Pertanyaan dari DB', $lastMsg['content']);
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

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Pertanyaan',
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

        $this->runExecuteStream($user, $conversation);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari streaming.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Idempotency — job selesai duluan, stream datang belakangan
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

        $this->runExecuteStream($user, $conversation);

        // Only one assistant message should exist
        $assistantCount = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->count();

        $this->assertSame(1, $assistantCount, 'Tidak boleh ada duplikat assistant message');
    }

    // -------------------------------------------------------------------------
    // Race condition: stream selesai duluan, job berjalan belakangan
    // -------------------------------------------------------------------------

    public function test_race_stream_first_then_job_produces_single_assistant_message(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Race test: stream first',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Pertanyaan race',
        ]);

        // Step 1: Stream selesai duluan dan menyimpan assistant message
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
                yield 'Jawaban dari stream.';
            }
        });

        $this->runExecuteStream($user, $conversation);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari stream.',
        ]);

        // Step 2: Job berjalan belakangan — harus skip karena stream sudah simpan
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
                yield 'Jawaban dari job (seharusnya tidak tersimpan).';
            }
        });

        $job = new GenerateChatResponse(
            conversationId: (int) $conversation->id,
            userId: (int) $user->id,
            history: [['role' => 'user', 'content' => 'Pertanyaan race']],
        );
        $job->handle(app(AIService::class), new ChatOrchestrationService);

        // Hanya satu assistant message yang boleh ada
        $assistantCount = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->count();

        $this->assertSame(1, $assistantCount, 'Race: stream selesai duluan, job tidak boleh duplikat');

        // Konten yang tersimpan harus dari stream, bukan job
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari stream.',
        ]);
        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari job (seharusnya tidak tersimpan).',
        ]);
    }

    public function test_race_job_first_then_stream_produces_single_assistant_message(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Race test: job first',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Pertanyaan race job first',
        ]);

        // Step 1: Job selesai duluan
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
                yield 'Jawaban dari job.';
            }
        });

        $job = new GenerateChatResponse(
            conversationId: (int) $conversation->id,
            userId: (int) $user->id,
            history: [['role' => 'user', 'content' => 'Pertanyaan race job first']],
        );
        $job->handle(app(AIService::class), new ChatOrchestrationService);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari job.',
        ]);

        // Step 2: Stream selesai belakangan — harus skip karena job sudah simpan
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
                yield 'Jawaban dari stream (seharusnya tidak tersimpan).';
            }
        });

        $this->runExecuteStream($user, $conversation);

        // Hanya satu assistant message yang boleh ada
        $assistantCount = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->count();

        $this->assertSame(1, $assistantCount, 'Race: job selesai duluan, stream tidak boleh duplikat');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari job.',
        ]);
        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari stream (seharusnya tidak tersimpan).',
        ]);
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

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Pertanyaan',
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

        $body = $this->runExecuteStream($user, $conversation);

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

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Halo',
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
        ob_start();
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
        ob_get_clean();

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

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Pertanyaan',
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

        $body = $this->runExecuteStream($user, $conversation);

        $this->assertStringContainsString('event: message-id', $body);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Call executeStream() directly and capture its echo output.
     * History is reconstructed from DB messages (no query string).
     *
     * @param  array<int, int>  $documentIds
     */
    private function runExecuteStream(
        User $user,
        Conversation $conversation,
        array $documentIds = [],
        bool $webSearchMode = false,
    ): string {
        $this->actingAs($user);

        $orchestrator = app(ChatOrchestrationService::class);
        $docContext = $orchestrator->getActiveDocumentContext($documentIds);

        // Reconstruct history from DB (same as controller does)
        $dbMessages = \App\Models\Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id', 'asc')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => (string) $m->role, 'content' => (string) $m->content])
            ->all();
        $history = $orchestrator->buildHistory($dbMessages);

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
