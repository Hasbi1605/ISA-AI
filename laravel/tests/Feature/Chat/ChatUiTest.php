<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee(':disabled="isNavigating"', false)
            ->assertSee('wire:key="chat-history-visible-', false);
    }
}
