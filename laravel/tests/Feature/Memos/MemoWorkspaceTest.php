<?php

namespace Tests\Feature\Memos;

use App\Livewire\Memos\MemoWorkspace;
use App\Models\Memo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MemoWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_renders_parent_driven_toggle_and_home_link(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->assertSee('Kembali ke Beranda', false)
            ->assertSee('New Memo', false)
            ->assertSee('Pengaturan Akun', false)
            ->assertSee('chat-tab-switch', false)
            ->assertSee('activeTab === \'memo\'', false)
            ->assertSee('darkMode = !darkMode', false)
            ->assertSee('images/icons/collapse-left-light.svg', false)
            ->assertSee('chat-form', false)
            ->assertSee('ISTA AI dapat keliru', false)
            ->assertDontSee('Buat Memo Baru', false)
            ->assertDontSee('wire:click="$set(\'tab\', \'chat\')"', false);
    }

    public function test_workspace_sidebar_groups_memo_history_like_chat_sidebar(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $todayMemo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Hari Ini',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
        ]);
        $todayMemo->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $recentMemo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Minggu Ini',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
        ]);
        $recentMemo->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->save();

        $olderMemo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Lama',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
        ]);
        $olderMemo->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->save();

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->assertSee('Today', false)
            ->assertSee('Previous 7 Days', false)
            ->assertSee('Older', false)
            ->assertSee('chat-history-item', false)
            ->assertSee('data-memo-history-id=', false)
            ->assertSee('Memo Hari Ini', false)
            ->assertSee('Memo Minggu Ini', false)
            ->assertSee('Memo Lama', false);
    }

    public function test_loading_memo_history_does_not_refresh_timestamp_or_move_sidebar_group(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $oldTimestamp = now()->subDays(10)->setTime(9, 0);

        Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Hari Ini',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $olderMemo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Tetap Lama',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
        ]);
        $olderMemo->forceFill([
            'created_at' => $oldTimestamp,
            'updated_at' => $oldTimestamp,
        ])->save();

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('loadMemo', $olderMemo->id)
            ->assertSee('Older', false)
            ->assertSee('Memo Tetap Lama', false);

        $this->assertSame(
            $oldTimestamp->format('Y-m-d H:i:s'),
            $olderMemo->refresh()->updated_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_preview_mode_can_switch_back_from_editor_to_preview(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('switchPreviewMode', 'editor')
            ->assertSet('previewMode', 'editor')
            ->call('switchPreviewMode', 'preview')
            ->assertSet('previewMode', 'preview');
    }

    public function test_generated_memo_chat_thread_is_restored_when_loading_memo_history(): void
    {
        Storage::fake('local');
        Http::fake([
            '*/api/memos/generate-body' => Http::response('docx-bytes', 200, [
                'X-Memo-Searchable-Text-B64' => base64_encode('Isi memo rapat lingkungan'),
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $component = Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->set('memoType', 'memo_internal')
            ->set('title', 'Rapat Lingkungan')
            ->set('memoPrompt', 'Buatkan memo rapat lingkungan')
            ->call('sendMemoChat')
            ->assertHasNoErrors()
            ->assertSee('Buatkan memo rapat lingkungan', false)
            ->assertSee('berhasil digenerate', false);

        $memo = Memo::firstOrFail();
        $storedMessages = $memo->refresh()->chat_messages;

        $this->assertIsArray($storedMessages);
        $this->assertTrue(collect($storedMessages)->contains(
            fn (array $message) => $message['role'] === 'user'
                && $message['content'] === 'Buatkan memo rapat lingkungan'
        ));

        $component
            ->call('startNewMemo')
            ->assertDontSee('Buatkan memo rapat lingkungan', false)
            ->call('loadMemo', $memo->id)
            ->assertSee('Buatkan memo rapat lingkungan', false)
            ->assertSee('berhasil digenerate', false);

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('loadMemo', $memo->id)
            ->assertSee('Buatkan memo rapat lingkungan', false)
            ->assertSee('berhasil digenerate', false);
    }
}
