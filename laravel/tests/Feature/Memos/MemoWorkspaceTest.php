<?php

namespace Tests\Feature\Memos;

use App\Livewire\Memos\MemoWorkspace;
use App\Models\Memo;
use App\Models\MemoVersion;
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
            ->assertSee('Format dokumen', false)
            ->assertSee('Sedang membuat memo', false)
            ->assertSee('Generate Memo', false)
            ->assertSee('Arahan Tambahan', false)
            ->assertSee('memo-document-ready.window', false)
            ->assertSee('dashboard-grid.png', false)
            ->assertSee('aria-label="Buat memo baru"', false)
            ->assertSee('title="Buat memo baru"', false)
            ->assertSee('bg-transparent overflow-hidden', false)
            ->assertSee('h-[61px] flex-shrink-0 flex items-center justify-between gap-2 px-3 sm:px-6 z-20', false)
            ->assertSee('ISTA AI dapat keliru', false)
            ->assertDontSee('min-h-[61px] flex-shrink-0 flex items-center justify-between gap-2 px-3 sm:px-5 border-b border-stone-200/60 dark:border-[#1E293B]/70 bg-white/85 dark:bg-gray-800/85', false)
            ->assertDontSee('dark:bg-gray-950/85', false)
            ->assertDontSee('Dokumen resmi', false)
            ->assertDontSee('Format resmi', false)
            ->assertDontSee('Nota Dinas', false)
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
            ->assertSee('wire:click="deleteMemo', false)
            ->assertSee('data-memo-history-id=', false)
            ->assertSee('Memo Hari Ini', false)
            ->assertSee('Memo Minggu Ini', false)
            ->assertSee('Memo Lama', false)
            ->assertDontSee('Memo Internal', false);
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

    public function test_generate_configuration_validation_is_visible_near_generate_button(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->set('memoRecipient', 'Kepala Subbagian Tata Usaha')
            ->set('memoSender', 'Kepala Istana Kepresidenan Yogyakarta')
            ->set('title', 'Rapat Lingkungan')
            ->set('memoDate', '5 Mei 2026')
            ->set('memoContent', 'Buatkan memo rapat lingkungan dengan poin peserta dan jadwal.')
            ->set('memoSignatory', 'Deni Mulyana')
            ->call('generateConfiguredMemo')
            ->assertHasErrors('memoNumber')
            ->assertDispatched('memo-configuration-invalid')
            ->assertSee('Belum bisa generate memo.', false)
            ->assertSee('Nomor memo wajib diisi.', false);
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
                'X-Memo-Page-Size' => 'letter',
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
            ->set('memoClosing', '')
            ->set('memoSignatory', 'Deni Mulyana')
            ->set('memoCarbonCopy', 'Kepala Bagian Tata Usaha')
            ->set('memoPageSize', 'auto')
            ->call('generateConfiguredMemo')
            ->assertHasNoErrors()
            ->assertSee('Konfigurasi memo:', false)
            ->assertSee('berhasil digenerate', false)
            ->assertDispatched('memo-document-ready');

        $memo = Memo::firstOrFail();
        $storedMessages = $memo->refresh()->chat_messages;

        $this->assertIsArray($storedMessages);
        $this->assertSame(1, $memo->versions()->count());
        $this->assertNotNull($memo->current_version_id);
        $this->assertSame('M-03/I-Yog/UM.01/05/2026', $memo->configuration['number']);
        $this->assertSame('Kepala Subbagian Tata Usaha', $memo->configuration['recipient']);
        $this->assertSame('letter', $memo->configuration['page_size']);
        $this->assertSame('auto', $memo->configuration['page_size_mode']);
        $this->assertArrayNotHasKey('closing', $memo->configuration);
        Http::assertSent(fn ($request) => $request['configuration']['number'] === 'M-03/I-Yog/UM.01/05/2026'
            && $request['configuration']['recipient'] === 'Kepala Subbagian Tata Usaha'
            && $request['configuration']['content'] === 'Buatkan memo rapat lingkungan dengan poin peserta dan jadwal.'
            && $request['configuration']['page_size'] === 'auto'
            && $request['configuration']['page_size_mode'] === 'auto'
            && ! array_key_exists('closing', $request['configuration']));
        $this->assertTrue(collect($storedMessages)->contains(
            fn (array $message) => $message['role'] === 'user'
                && str_contains($message['content'], 'Nomor: M-03/I-Yog/UM.01/05/2026')
        ));

        $component
            ->call('startNewMemo')
            ->assertDontSee('M-03/I-Yog/UM.01/05/2026', false)
            ->assertSet('memoClosing', '')
            ->assertSet('memoPageSize', 'auto')
            ->call('loadMemo', $memo->id)
            ->assertSet('memoNumber', 'M-03/I-Yog/UM.01/05/2026')
            ->assertSet('memoRecipient', 'Kepala Subbagian Tata Usaha')
            ->assertSet('memoClosing', '')
            ->assertSet('memoPageSize', 'auto')
            ->assertSee('M-03/I-Yog/UM.01/05/2026', false)
            ->assertSee('berhasil digenerate', false);

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('loadMemo', $memo->id)
            ->assertSet('memoNumber', 'M-03/I-Yog/UM.01/05/2026')
            ->assertSee('M-03/I-Yog/UM.01/05/2026', false)
            ->assertSee('berhasil digenerate', false);
    }

    public function test_generate_configuration_preserves_manual_closing_for_ai_service(): void
    {
        Storage::fake('local');
        Http::fake([
            '*/api/memos/generate-body' => Http::response('docx-bytes', 200, [
                'X-Memo-Searchable-Text-B64' => base64_encode('Isi memo dengan penutup manual'),
                'X-Memo-Page-Size' => 'letter',
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $closing = 'Demikian disampaikan, atas perhatian dan kerja samanya diucapkan terima kasih.';

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->set('memoType', 'memo_internal')
            ->set('memoNumber', 'EVAL-11/IST/YK/05/2026')
            ->set('memoRecipient', 'Kepala Bagian SDM')
            ->set('memoSender', 'Kepala Istana Kepresidenan Yogyakarta')
            ->set('title', 'Penyampaian Data Pegawai Pendamping Kegiatan')
            ->set('memoDate', '7 Mei 2026')
            ->set('memoBasis', 'Untuk kebutuhan pendampingan kegiatan integrasi aplikasi.')
            ->set('memoContent', 'Nama: Muhammad Hasbi Ash Shiddiqi'.PHP_EOL.'NIP: 231210013')
            ->set('memoClosing', $closing)
            ->set('memoSignatory', 'Deni Mulyana')
            ->set('memoPageSize', 'auto')
            ->call('generateConfiguredMemo')
            ->assertHasNoErrors()
            ->assertDispatched('memo-document-ready');

        $memo = Memo::firstOrFail();

        $this->assertSame($closing, $memo->configuration['closing']);
        Http::assertSent(fn ($request) => $request['configuration']['closing'] === $closing);
    }

    public function test_generate_configuration_on_active_memo_creates_new_version_without_new_history(): void
    {
        Storage::fake('local');
        config([
            'services.onlyoffice.jwt_secret' => 'workspace-secret',
            'services.onlyoffice.laravel_internal_url' => 'http://laravel:8000',
        ]);
        Http::fake([
            '*/api/memos/generate-body' => Http::response('docx-bytes', 200, [
                'X-Memo-Searchable-Text-B64' => base64_encode('Memo hasil konfigurasi'),
                'X-Memo-Page-Size' => 'letter',
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $component = Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->set('memoType', 'memo_internal')
            ->set('memoNumber', 'EVAL-19/IST/YK/05/2026')
            ->set('memoRecipient', 'Kepala Unit Layanan')
            ->set('memoSender', 'Kepala Istana Kepresidenan Yogyakarta')
            ->set('title', 'Penyampaian Kontak PIC Layanan')
            ->set('memoDate', '7 Mei 2026')
            ->set('memoBasis', 'Untuk mempercepat koordinasi layanan internal.')
            ->set('memoContent', 'Nama: Eko Prasetyo'.PHP_EOL.'NIP: 199411172025211057')
            ->set('memoClosing', 'Demikian disampaikan, atas perhatian dan kerja samanya diucapkan terima kasih.')
            ->set('memoSignatory', 'Deni Mulyana')
            ->set('memoPageSize', 'auto')
            ->call('generateConfiguredMemo')
            ->assertHasNoErrors()
            ->assertDispatched('memo-document-ready');

        $memo = Memo::firstOrFail();
        $firstVersionId = $memo->current_version_id;

        $component
            ->set('memoContent', 'Nama: Eko Prasetyo'.PHP_EOL.'NIP: 199411172025211057'.PHP_EOL.'Keperluan: Koordinasi layanan internal.')
            ->call('generateConfiguredMemo')
            ->assertHasNoErrors()
            ->assertSee('Versi 2', false)
            ->assertSee('History tetap berada pada memo yang sama', false)
            ->assertDispatched('memo-document-ready');

        $memo->refresh();

        $this->assertSame(1, Memo::count());
        $this->assertSame(2, MemoVersion::where('memo_id', $memo->id)->count());
        $this->assertNotSame($firstVersionId, $memo->current_version_id);
        $this->assertSame('Keperluan: Koordinasi layanan internal.', str($memo->configuration['content'])->afterLast(PHP_EOL)->toString());
        $this->assertStringContainsString('Generate ulang memo aktif dari konfigurasi terbaru', $memo->configuration['revision_instruction']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => ($request['configuration']['revision_instruction'] ?? '') !== ''
            && $request['configuration']['content'] === "Nama: Eko Prasetyo\nNIP: 199411172025211057\nKeperluan: Koordinasi layanan internal."
            && str_contains($request['context'], 'Isi/poin wajib:')
            && ! str_contains($request['context'], 'Isi memo saat ini:'));
    }

    public function test_loading_memo_without_optional_closing_clears_stale_form_values(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $closing = 'Demikian disampaikan, atas perhatian dan kerja samanya diucapkan terima kasih.';

        $memoWithClosing = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Dengan Penutup',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [
                'number' => 'EVAL-01/IST/YK/05/2026',
                'recipient' => 'Kepala Bagian SDM',
                'sender' => 'Kepala Istana Kepresidenan Yogyakarta',
                'subject' => 'Memo Dengan Penutup',
                'date' => '7 Mei 2026',
                'content' => 'Isi memo pertama.',
                'closing' => $closing,
                'additional_instruction' => 'Gunakan bahasa sangat formal.',
                'signatory' => 'Deni Mulyana',
                'page_size' => 'letter',
            ],
        ]);

        $memoWithoutClosing = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Tanpa Penutup',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [
                'number' => 'EVAL-02/IST/YK/05/2026',
                'recipient' => 'Kepala Bagian Administrasi',
                'sender' => 'Kepala Istana Kepresidenan Yogyakarta',
                'subject' => 'Memo Tanpa Penutup',
                'date' => '7 Mei 2026',
                'content' => 'Isi memo kedua.',
                'signatory' => 'Deni Mulyana',
                'page_size' => 'letter',
            ],
        ]);

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('loadMemo', $memoWithClosing->id)
            ->assertSet('memoClosing', $closing)
            ->assertSet('memoAdditionalInstruction', 'Gunakan bahasa sangat formal.')
            ->call('loadMemo', $memoWithoutClosing->id)
            ->assertSet('memoClosing', '')
            ->assertSet('memoAdditionalInstruction', '');
    }

    public function test_revision_chat_applies_carbon_copy_instruction_before_regenerating(): void
    {
        Storage::fake('local');
        config([
            'services.onlyoffice.jwt_secret' => 'workspace-secret',
            'services.onlyoffice.laravel_internal_url' => 'http://laravel:8000',
        ]);
        Http::fake([
            '*/api/memos/generate-body' => Http::response('revised-docx-bytes', 200, [
                'X-Memo-Searchable-Text-B64' => base64_encode('Memo revisi dengan tembusan baru'),
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Penyampaian Keberatan Untuk Keperluan tersebut',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo-awal.docx',
            'status' => Memo::STATUS_GENERATED,
            'searchable_text' => "Isi memo saat ini.\nTembusan:\n1. Kepala Dinas Sekretariat Negara\n2. Kepala IKY\n3. Kepala KOMDIGI",
            'configuration' => [
                'number' => 'M/2312/22D/409L/YK',
                'recipient' => 'Kepala Komdigi',
                'sender' => 'Kepala Istana Kepresidenan Yogyakarta',
                'subject' => 'Penyampaian Keberatan Untuk Keperluan tersebut',
                'date' => '6 Mei 2026',
                'content' => 'Menindaklanjuti proses pemindahan pegawai IT.',
                'signatory' => 'Deni Mulyana',
                'carbon_copy' => "Kepala Dinas Sekretariat Negara\nKepala IKY\nKepala KOMDIGI",
                'page_size' => 'letter',
                'page_size_mode' => 'auto',
            ],
        ]);
        $originalVersion = $memo->versions()->create([
            'version_number' => 1,
            'label' => 'Versi 1',
            'file_path' => $memo->file_path,
            'status' => Memo::STATUS_GENERATED,
            'configuration' => $memo->configuration,
            'searchable_text' => $memo->searchable_text,
        ]);
        $memo->forceFill(['current_version_id' => $originalVersion->id])->save();

        $component = Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('loadMemo', $memo->id)
            ->set('memoPrompt', 'tambahkan tembusan nomor 4, untuk Kepala Istana Kapak')
            ->call('sendMemoChat')
            ->assertHasNoErrors()
            ->assertSee('Revisi memo', false)
            ->assertSee('Versi 2', false)
            ->assertDispatched('memo-document-ready');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/memos/generate-body')
            && $request['configuration']['carbon_copy'] === "1. Kepala Dinas Sekretariat Negara\n2. Kepala IKY\n3. Kepala KOMDIGI\n4. Kepala Istana Kapak"
            && $request['configuration']['revision_instruction'] === 'tambahkan tembusan nomor 4, untuk Kepala Istana Kapak'
            && str_contains($request['context'], 'Isi memo saat ini')
            && str_contains($request['context'], 'Instruksi revisi wajib diterapkan')
            && ! str_contains($request['context'], 'Tembusan:')
            && ! str_contains($request['context'], 'Deni Mulyana'));

        $revisedMemo = $memo->refresh();

        $this->assertSame(1, Memo::count());
        $this->assertSame(2, MemoVersion::where('memo_id', $memo->id)->count());
        $this->assertSame(
            "1. Kepala Dinas Sekretariat Negara\n2. Kepala IKY\n3. Kepala KOMDIGI\n4. Kepala Istana Kapak",
            $revisedMemo->configuration['carbon_copy'],
        );
        $this->assertSame('tambahkan tembusan nomor 4, untuk Kepala Istana Kapak', $revisedMemo->configuration['revision_instruction']);
        $this->assertSame($memo->id, $revisedMemo->id);
        $this->assertNotSame($originalVersion->id, $revisedMemo->current_version_id);
        $currentVersionId = $revisedMemo->current_version_id;
        $currentFilePath = $revisedMemo->file_path;

        $component
            ->call('switchMemoVersion', $originalVersion->id)
            ->assertSet('activeMemoVersionId', $originalVersion->id)
            ->assertSet('memoCarbonCopy', "Kepala Dinas Sekretariat Negara\nKepala IKY\nKepala KOMDIGI");

        $memo->refresh();
        $this->assertSame($currentVersionId, $memo->current_version_id);
        $this->assertSame($currentFilePath, $memo->file_path);

        $editorConfig = $component->instance()->editorConfig();
        parse_str((string) parse_url($editorConfig['document']['url'], PHP_URL_QUERY), $documentQuery);
        parse_str((string) parse_url($editorConfig['editorConfig']['callbackUrl'], PHP_URL_QUERY), $callbackQuery);

        $this->assertSame((string) $originalVersion->id, $documentQuery['version_id']);
        $this->assertSame((string) $originalVersion->id, $callbackQuery['version_id']);
        $this->assertStringStartsWith('memo-'.$memo->id.'-v'.$originalVersion->id.'-', $editorConfig['document']['key']);
    }

    public function test_generate_configuration_allows_blank_signatory_and_preserves_it_for_ai_service(): void
    {
        Storage::fake('local');
        Http::fake([
            '*/api/memos/generate-body' => Http::response('docx-bytes', 200, [
                'X-Memo-Searchable-Text-B64' => base64_encode('Memo tanpa nama penandatangan'),
                'X-Memo-Page-Size' => 'letter',
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->set('memoType', 'memo_internal')
            ->set('memoNumber', 'EVAL-21/IST/YK/05/2026')
            ->set('memoRecipient', 'Kepala Subbagian Persuratan')
            ->set('memoSender', 'Kepala Istana Kepresidenan Yogyakarta')
            ->set('title', 'Konfirmasi Kehadiran Rapat Singkat')
            ->set('memoDate', '7 Mei 2026')
            ->set('memoContent', 'Konfirmasi kehadiran rapat singkat.')
            ->set('memoSignatory', '')
            ->set('memoPageSize', 'letter')
            ->call('generateConfiguredMemo')
            ->assertHasNoErrors()
            ->assertDispatched('memo-document-ready');

        $memo = Memo::firstOrFail();

        $this->assertArrayHasKey('signatory', $memo->configuration);
        $this->assertSame('', $memo->configuration['signatory']);
        Http::assertSent(fn ($request) => $request['configuration']['signatory'] === '');
    }

    public function test_revision_chat_applies_recipient_instruction_before_regenerating(): void
    {
        Storage::fake('local');
        Http::fake([
            '*/api/memos/generate-body' => Http::response('revised-docx-bytes', 200, [
                'X-Memo-Searchable-Text-B64' => base64_encode('Memo revisi penerima'),
                'X-Memo-Page-Size' => 'letter',
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Revisi Ubah Penerima Memo',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo-awal.docx',
            'status' => Memo::STATUS_GENERATED,
            'searchable_text' => "KEMENTERIAN SEKRETARIAT NEGARA RI\nMEMORANDUM\nNomor EVAL-32/IST/YK/05/2026\nYth.    : Kepala Bagian Administrasi\nDari    : Kepala Istana Kepresidenan Yogyakarta\nHal     : Revisi Ubah Penerima Memo\nTanggal : 7 Mei 2026\nIsi memo saat ini tetap dipertahankan.\nDeni Mulyana\nTembusan:\nKepala Bagian Protokol",
            'configuration' => [
                'number' => 'EVAL-32/IST/YK/05/2026',
                'recipient' => 'Kepala Bagian Administrasi',
                'sender' => 'Kepala Istana Kepresidenan Yogyakarta',
                'subject' => 'Revisi Ubah Penerima Memo',
                'date' => '7 Mei 2026',
                'content' => 'Isi memo saat ini tetap dipertahankan.',
                'signatory' => 'Deni Mulyana',
                'carbon_copy' => 'Kepala Bagian Protokol',
                'page_size' => 'letter',
                'page_size_mode' => 'auto',
            ],
        ]);
        $version = $memo->versions()->create([
            'version_number' => 1,
            'label' => 'Versi 1',
            'file_path' => $memo->file_path,
            'status' => Memo::STATUS_GENERATED,
            'configuration' => $memo->configuration,
            'searchable_text' => $memo->searchable_text,
        ]);
        $memo->forceFill(['current_version_id' => $version->id])->save();

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('loadMemo', $memo->id)
            ->set('memoPrompt', 'ubah nama penerima memo menjadi Kepala Pusat Pengembangan Layanan')
            ->call('sendMemoChat')
            ->assertHasNoErrors()
            ->assertDispatched('memo-document-ready');

        Http::assertSent(fn ($request) => $request['configuration']['recipient'] === 'Kepala Pusat Pengembangan Layanan'
            && str_contains($request['context'], 'Isi memo saat ini tetap dipertahankan.')
            && ! str_contains($request['context'], 'Tembusan:')
            && ! str_contains($request['context'], 'Deni Mulyana'));
        $this->assertSame('Kepala Pusat Pengembangan Layanan', $memo->refresh()->configuration['recipient']);
    }

    public function test_revision_chat_applies_new_date_when_instruction_mentions_old_and_new_dates(): void
    {
        Storage::fake('local');
        Http::fake([
            '*/api/memos/generate-body' => Http::response('revised-docx-bytes', 200, [
                'X-Memo-Searchable-Text-B64' => base64_encode('Memo revisi tanggal'),
                'X-Memo-Page-Size' => 'letter',
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Revisi Tanggal Kegiatan',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo-awal.docx',
            'status' => Memo::STATUS_GENERATED,
            'searchable_text' => "Isi memo saat ini menyebut kegiatan pada 12 Mei 2026.\nDeni Mulyana",
            'configuration' => [
                'number' => 'EVAL-39/IST/YK/05/2026',
                'recipient' => 'Kepala Bagian Protokol',
                'sender' => 'Kepala Istana Kepresidenan Yogyakarta',
                'subject' => 'Revisi Tanggal Kegiatan',
                'date' => '12 Mei 2026',
                'content' => 'Isi memo saat ini menyebut kegiatan pada 12 Mei 2026.',
                'signatory' => 'Deni Mulyana',
                'page_size' => 'letter',
                'page_size_mode' => 'auto',
            ],
        ]);
        $version = $memo->versions()->create([
            'version_number' => 1,
            'label' => 'Versi 1',
            'file_path' => $memo->file_path,
            'status' => Memo::STATUS_GENERATED,
            'configuration' => $memo->configuration,
            'searchable_text' => $memo->searchable_text,
        ]);
        $memo->forceFill(['current_version_id' => $version->id])->save();

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('loadMemo', $memo->id)
            ->set('memoPrompt', 'ubah tanggal kegiatan dari 12 Mei 2026 menjadi 13 Mei 2026')
            ->call('sendMemoChat')
            ->assertHasNoErrors()
            ->assertDispatched('memo-document-ready');

        Http::assertSent(fn ($request) => $request['configuration']['date'] === '13 Mei 2026');
        $this->assertSame('13 Mei 2026', $memo->refresh()->configuration['date']);
    }

    public function test_memo_history_can_be_deleted_like_chat_history(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Untuk Dihapus',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
            'chat_messages' => [
                ['role' => 'assistant', 'content' => 'Memo siap.', 'timestamp' => '10:00'],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(MemoWorkspace::class)
            ->call('loadMemo', $memo->id)
            ->call('deleteMemo', $memo->id)
            ->assertSet('activeMemoId', null)
            ->assertSet('activeMemoVersionId', null)
            ->assertSee('Konfigurasi Memo', false)
            ->assertDontSee('Memo Untuk Dihapus', false);

        $this->assertSoftDeleted('memos', ['id' => $memo->id]);
    }
}
