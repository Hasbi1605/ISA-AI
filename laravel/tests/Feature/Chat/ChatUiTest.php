<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_page_renders_multiline_and_document_feedback_hooks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/chat');

        $response
            ->assertOk()
            ->assertSee('x-on:keydown.enter="handleEnterKey($event)"', false)
            ->assertSee('x-show="!optimisticUserMessage"', false)
            ->assertSee('Menghapus dokumen...', false);
    }
}
