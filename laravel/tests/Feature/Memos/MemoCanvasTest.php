<?php

namespace Tests\Feature\Memos;

use App\Livewire\Memos\MemoCanvas;
use App\Models\Memo;
use App\Models\User;
use App\Services\OnlyOffice\JwtSigner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MemoCanvasTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_creates_memo_and_redirects_to_canvas(): void
    {
        Storage::fake('local');
        Http::fake([
            '*/api/memos/generate-body' => Http::response('docx-bytes', 200, [
                'X-Memo-Searchable-Text-B64' => base64_encode('Memo Test searchable'),
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);

        Livewire::actingAs($user)
            ->test(MemoCanvas::class)
            ->set('memoType', 'memo_internal')
            ->set('title', 'Memo Test')
            ->set('context', 'Buat memo rapat koordinasi.')
            ->call('generate')
            ->assertHasNoErrors();

        $memo = Memo::firstOrFail();

        $this->assertSame($user->id, $memo->user_id);
        $this->assertSame(Memo::STATUS_GENERATED, $memo->status);
        $this->assertNotNull($memo->file_path);
        Storage::disk('local')->assertExists($memo->file_path);
    }

    public function test_canvas_forbids_non_owner(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        $memo = Memo::create([
            'user_id' => $owner->id,
            'title' => 'Memo Rahasia',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$owner->id.'/memo.docx',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $this->actingAs($other)
            ->get(route('memos.edit', $memo))
            ->assertForbidden();
    }

    public function test_editor_token_signs_exact_onlyoffice_config_shape(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'editor-secret',
            'services.onlyoffice.laravel_internal_url' => 'http://laravel:8000',
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Editor',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo.docx',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $component = Livewire::actingAs($user)
            ->test(MemoCanvas::class, ['memo' => $memo]);

        $config = $component->instance()->editorConfig();
        $token = $config['token'];
        unset($config['token']);

        $this->assertSame($config, (new JwtSigner('editor-secret'))->verify($token));
    }

    public function test_editor_document_url_uses_configured_signed_url_ttl(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-02 10:00:00'));

        config([
            'services.onlyoffice.jwt_secret' => 'editor-secret',
            'services.onlyoffice.laravel_internal_url' => 'http://laravel:8000',
            'services.onlyoffice.signed_url_ttl_minutes' => 17,
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo TTL',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo.docx',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $component = Livewire::actingAs($user)
            ->test(MemoCanvas::class, ['memo' => $memo]);

        $config = $component->instance()->editorConfig();
        parse_str((string) parse_url($config['document']['url'], PHP_URL_QUERY), $query);

        $this->assertSame((string) now()->addMinutes(17)->getTimestamp(), $query['expires']);

        Carbon::setTestNow();
    }
}
