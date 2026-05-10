<?php

namespace Tests\Feature\Memos;

use App\Models\Memo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoRouteRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_memo_index_redirects_to_chat_memo_tab(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('memos.index'))
            ->assertRedirect(route('chat', ['tab' => 'memo']));
    }

    public function test_standalone_memo_create_redirects_to_chat_memo_tab(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('memos.create'))
            ->assertRedirect(route('chat', ['tab' => 'memo']));
    }

    public function test_standalone_memo_edit_redirects_to_chat_memo_tab(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Test',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo.docx',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $this->actingAs($user)
            ->get(route('memos.edit', $memo))
            ->assertRedirect(route('chat', ['tab' => 'memo']));
    }

    public function test_legacy_memo_download_path_redirects_to_chat_memo_tab(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Test',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo.docx',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $this->actingAs($user)
            ->get('/memos/'.$memo->id.'/download')
            ->assertRedirect(route('chat', ['tab' => 'memo']));
    }

    public function test_guest_standalone_memos_still_requires_login(): void
    {
        $this->get(route('memos.index'))
            ->assertRedirect(route('login'));
    }
}
