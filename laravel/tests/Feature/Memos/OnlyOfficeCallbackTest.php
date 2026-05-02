<?php

namespace Tests\Feature\Memos;

use App\Models\Memo;
use App\Models\User;
use App\Services\OnlyOffice\JwtSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnlyOfficeCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_rejects_missing_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);

        $this->postJson(route('onlyoffice.callback', $memo), [
            'status' => 2,
            'key' => 'memo-'.$memo->id.'-123',
            'url' => 'https://onlyoffice.test/file.docx',
        ])->assertUnauthorized();
    }

    public function test_callback_with_valid_token_saves_file(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'callback-secret',
            'services.onlyoffice.internal_url' => 'https://onlyoffice.test',
        ]);
        Storage::fake('local');
        Http::fake([
            'https://onlyoffice.test/file.docx' => Http::response('updated-docx', 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $key = 'memo-'.$memo->id.'-123';
        $url = 'https://onlyoffice.test/file.docx';
        $token = (new JwtSigner('callback-secret'))->sign([
            'status' => 2,
            'key' => $key,
            'url' => $url,
            'exp' => time() + 60,
        ]);

        $this->postJson(route('onlyoffice.callback', $memo), [
            'status' => 2,
            'key' => $key,
            'url' => $url,
            'token' => $token,
        ])->assertOk()
            ->assertJson(['error' => 0]);

        $memo->refresh();
        $this->assertSame(Memo::STATUS_EDITED, $memo->status);
        Storage::disk('local')->assertExists($memo->file_path);
        $this->assertSame('updated-docx', Storage::disk('local')->get($memo->file_path));
    }

    public function test_editor_config_token_cannot_be_used_as_callback_token(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'callback-secret',
            'services.onlyoffice.internal_url' => 'https://onlyoffice.test',
        ]);
        Storage::fake('local');
        Http::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $key = 'memo-'.$memo->id.'-123';
        $url = 'https://onlyoffice.test/file.docx';
        $token = (new JwtSigner('callback-secret'))->sign([
            'document' => [
                'key' => $key,
                'url' => route('memos.file.signed', $memo),
            ],
            'documentType' => 'word',
            'editorConfig' => [
                'callbackUrl' => route('onlyoffice.callback', $memo),
            ],
            'token_use' => 'editor_config',
            'exp' => time() + 60,
        ]);

        $this->postJson(route('onlyoffice.callback', $memo), [
            'status' => 2,
            'key' => $key,
            'url' => $url,
            'token' => $token,
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_callback_rejects_tampered_token(): void
    {
        config(['services.onlyoffice.jwt_secret' => 'callback-secret']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $key = 'memo-'.$memo->id.'-123';
        $url = 'https://onlyoffice.test/file.docx';
        $token = (new JwtSigner('callback-secret'))->sign([
            'status' => 2,
            'key' => $key,
            'url' => $url,
            'exp' => time() + 60,
        ]);

        $this->postJson(route('onlyoffice.callback', $memo), [
            'status' => 2,
            'key' => $key,
            'url' => $url,
            'token' => $token.'x',
        ])->assertUnauthorized();
    }

    public function test_callback_rejects_untrusted_download_url(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'callback-secret',
            'services.onlyoffice.internal_url' => 'https://onlyoffice.test',
        ]);
        Http::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $key = 'memo-'.$memo->id.'-123';
        $url = 'https://evil.test/file.docx';
        $token = (new JwtSigner('callback-secret'))->sign([
            'status' => 2,
            'key' => $key,
            'url' => $url,
            'exp' => time() + 60,
        ]);

        $this->postJson(route('onlyoffice.callback', $memo), [
            'status' => 2,
            'key' => $key,
            'url' => $url,
            'token' => $token,
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    protected function createMemo(User $user): Memo
    {
        return Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Test',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo.docx',
            'status' => Memo::STATUS_GENERATED,
        ]);
    }
}
