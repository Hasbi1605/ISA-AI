<?php

namespace Tests\Feature\Chat;

use App\Livewire\Chat\ChatIndex;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChatUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_page_renders_multiline_and_document_feedback_hooks(): void
    {
        $user = User::factory()->create();
        Conversation::create([
            'user_id' => $user->id,
            'title' => 'History test',
        ]);

        $response = $this->actingAs($user)->get('/chat');

        $response
            ->assertOk()
            ->assertSee('x-on:keydown.enter="handleEnterKey($event)"', false)
            ->assertSee('x-show="!optimisticUserMessage && !isSwitchingConversation"', false)
            ->assertSee('Menghapus dokumen...', false)
            ->assertSee('conversation-documents-preview', false)
            ->assertSee('Membuka chat...', false)
            ->assertSee('x-on:conversation-loading.window="isSwitchingConversation = true"', false)
            ->assertSee('x-on:conversation-activated.window="setActiveConversation($event.detail.id)"', false)
            ->assertSee(':disabled="isNavigating"', false)
            ->assertSee('data-chat-history-id=', false)
            ->assertSee('chat-history-item', false)
            ->assertSee('wire:key="chat-history-visible-', false)
            ->assertSee('openGoogleDrivePicker()', false)
            ->assertSee('open-google-drive-picker', false)
            ->assertSee('Ambil file dari Google Drive Kantor', false)
            ->assertSee('images/icons/google-drive.svg', false)
            ->assertSee('Ambil file untuk chat', false);
    }

    public function test_loading_conversation_dispatches_active_history_event(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Selected history',
        ]);

        Livewire::actingAs($user)
            ->test(ChatIndex::class)
            ->call('loadConversation', $conversation->id)
            ->assertSet('currentConversationId', $conversation->id)
            ->assertDispatched('conversation-activated', id: $conversation->id);
    }

    public function test_chat_answers_render_copy_share_and_export_actions(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Answer toolbar test',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => "Ini jawaban contoh.\n\n- Poin pertama\n- Poin kedua",
        ]);

        Livewire::actingAs($user)
            ->test(ChatIndex::class)
            ->set('messages', [$message->toArray()])
            ->assertSee('data-answer-actions', false)
            ->assertSee('Salin', false)
            ->assertSee('role="status"', false)
            ->assertSee('Tersalin', false)
            ->assertSee('Bagikan', false)
            ->assertDontSee('text-[#25D366]', false)
            ->assertSee('Upload ke Google Drive', false)
            ->assertSee('images/icons/google-drive.svg', false)
            ->assertSee('driveButtonLabel()', false)
            ->assertSee('Upload ke Drive', false)
            ->assertSee('Ekspor', false)
            ->assertSee('PDF', false)
            ->assertSee('DOCX', false)
            ->assertSee('XLSX', false)
            ->assertSee('CSV', false);
    }

    public function test_multiple_chat_answers_render_distinct_action_scopes(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Multiple answer toolbar test',
        ]);

        $firstMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban pertama untuk dibagikan.',
        ]);

        $secondMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban kedua harus punya tombol sendiri.',
        ]);

        Livewire::actingAs($user)
            ->test(ChatIndex::class)
            ->set('messages', [$firstMessage->toArray(), $secondMessage->toArray()])
            ->assertSee('wire:key="chat-message-'.$firstMessage->id.'"', false)
            ->assertSee('wire:key="chat-message-'.$secondMessage->id.'"', false)
            ->assertSee('wire:key="chat-answer-actions-'.$firstMessage->id.'"', false)
            ->assertSee('wire:key="chat-answer-actions-'.$secondMessage->id.'"', false)
            ->assertSee('data-answer-message-id="'.$firstMessage->id.'"', false)
            ->assertSee('data-answer-message-id="'.$secondMessage->id.'"', false);
    }

    public function test_google_drive_documents_show_source_label_in_sidebar(): void
    {
        $user = User::factory()->create();

        Document::create([
            'user_id' => $user->id,
            'filename' => 'surat-drive.pdf',
            'original_name' => 'surat-drive.pdf',
            'file_path' => 'documents/'.$user->id.'/surat-drive.pdf',
            'source_provider' => 'google_drive',
            'source_external_id' => 'drive-file-id',
            'source_synced_at' => now(),
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1234,
            'status' => 'ready',
            'preview_status' => Document::PREVIEW_STATUS_READY,
        ]);

        Livewire::actingAs($user)
            ->test(ChatIndex::class)
            ->assertSee('Google Drive', false)
            ->assertSee('surat-drive.pdf', false);
    }

    public function test_latest_chat_answer_still_renders_actions(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Latest answer toolbar test',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Jawaban terbaru juga punya toolbar.',
        ]);

        Livewire::actingAs($user)
            ->test(ChatIndex::class)
            ->set('messages', [$message->toArray()])
            ->set('newMessageId', $message->id)
            ->assertSee('data-answer-actions', false)
            ->assertSee('Salin', false)
            ->assertSee('Bagikan', false)
            ->assertSee('Ekspor', false);
    }

    public function test_loading_conversation_clears_latest_message_marker(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Clear latest marker test',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Pesan dari history.',
        ]);

        Livewire::actingAs($user)
            ->test(ChatIndex::class)
            ->set('newMessageId', $message->id)
            ->call('loadConversation', $conversation->id)
            ->assertSet('newMessageId', null);
    }
}
