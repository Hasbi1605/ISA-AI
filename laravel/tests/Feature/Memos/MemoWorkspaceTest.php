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
            ->assertSee('Konfigurasi Memo', false)
            ->assertSee('Nomor Memo', false)
            ->assertSee('Generate Memo', false)
            ->assertSee('ISTA AI dapat keliru', false)
            ->assertSee('dark:bg-gray-800/85', false)
            ->assertDontSee('dark:bg-gray-950/85', false)
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

    public function test_editor_config_ignores_tampered_active_memo_id_for_non_owner(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        $memo = Memo::create([
            'user_id' => $owner->id,
            'title' => 'Memo Rahasia Owner',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$owner->id.'/memo-rahasia.docx',
            'status' => Memo::STATUS_GENERATED,
        ]);

        Livewire::actingAs($other)
            ->test(MemoWorkspace::class)
            ->set('activeMemoId', $memo->id)
            ->assertSee('Dokumen belum tersedia', false)
            ->assertDontSee('Memo Rahasia Owner.docx', false)
            ->assertDontSee('memos/'.$memo->id.'/signed-file', false);
    }

    public function test_generated_memo_chat_thread_is_restored_when_loading_memo_history(): void
    {
        Storage::fake('local');
        config([
            'services.onlyoffice.jwt_secret' => 'workspace-secret',
            'services.onlyoffice.laravel_internal_url' => 'http://laravel:8000',
        ]);
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
            ->set('memoNumber', 'M-03/I-Yog/UM.01/05/2026')
            ->set('memoRecipient', 'Kepala Subbagian Tata Usaha')
            ->set('memoSender', 'Kepala Istana Kepresidenan Yogyakarta')
            ->set('title', 'Rapat Lingkungan')
            ->set('memoDate', '5 Mei 2026')
            ->set('memoBasis', 'Menindaklanjuti agenda koordinasi lingkungan.')
            ->set('memoContent', 'Buatkan memo rapat lingkungan dengan poin peserta dan jadwal.')
            ->set('memoClosing', 'Demikian, mohon arahan lebih lanjut.')
            ->set('memoSignatory', 'Deni Mulyana')
            ->set('memoCarbonCopy', 'Kepala Bagian Tata Usaha')
            ->set('memoPageSize', 'folio')
            ->call('generateConfiguredMemo')
            ->assertHasNoErrors()
            ->assertSee('Konfigurasi memo:', false)
            ->assertSee('berhasil digenerate', false);

        $memo = Memo::firstOrFail();
        $storedMessages = $memo->refresh()->chat_messages;

        $this->assertIsArray($storedMessages);
        $this->assertSame('M-03/I-Yog/UM.01/05/2026', $memo->configuration['number']);
        $this->assertSame('Kepala Subbagian Tata Usaha', $memo->configuration['recipient']);
        Http::assertSent(fn ($request) => $request['configuration']['number'] === 'M-03/I-Yog/UM.01/05/2026'
            && $request['configuration']['recipient'] === 'Kepala Subbagian Tata Usaha'
            && $request['configuration']['content'] === 'Buatkan memo rapat lingkungan dengan poin peserta dan jadwal.');
        $this->assertTrue(collect($storedMessages)->contains(
            fn (array $message) => $message['role'] === 'user'
                && str_contains($message['content'], 'Nomor: M-03/I-Yog/UM.01/05/2026')
        ));

        $component
            ->call('startNewMemo')
            ->assertDontSee('M-03/I-Yog/UM.01/05/2026', false)
            ->call('loadMemo', $memo->id)
            ->assertSet('memoNumber', 'M-03/I-Yog/UM.01/05/2026')
            ->assertSet('memoRecipient', 'Kepala Subbagian Tata Usaha')
            ->assertSee('M-03/I-Yog/UM.01/05/2026', false)
            ->assertSee('berhasil digenerate', false);

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('loadMemo', $memo->id)
            ->assertSet('memoNumber', 'M-03/I-Yog/UM.01/05/2026')
            ->assertSee('M-03/I-Yog/UM.01/05/2026', false)
            ->assertSee('berhasil digenerate', false);
    }
}
