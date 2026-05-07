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
        $this->assertSame(Memo::STATUS_EDITED, $memo->currentVersion?->status);
        $this->assertSame($memo->file_path, $memo->currentVersion?->file_path);
        Storage::disk('local')->assertExists($memo->file_path);
        $this->assertSame('updated-docx', Storage::disk('local')->get($memo->file_path));
    }

    public function test_callback_accepts_public_onlyoffice_download_url(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'callback-secret',
            'services.onlyoffice.internal_url' => 'http://onlyoffice',
            'services.onlyoffice.public_url' => 'https://ista-ai.app',
        ]);
        Storage::fake('local');
        Http::fake([
            'https://ista-ai.app/cache/files/data/output.docx*' => Http::response('public-docx', 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $key = 'memo-'.$memo->id.'-public';
        $url = 'https://ista-ai.app/cache/files/data/output.docx?md5=abc&expires=123';
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

        Storage::disk('local')->assertExists($memo->refresh()->file_path);
        $this->assertSame('public-docx', Storage::disk('local')->get($memo->file_path));
    }

    public function test_callback_accepts_onlyoffice_header_payload_wrapper(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'callback-secret',
            'services.onlyoffice.internal_url' => 'https://onlyoffice.test',
        ]);
        Storage::fake('local');
        Http::fake([
            'https://onlyoffice.test/header-file.docx' => Http::response('header-docx', 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $body = [
            'status' => 2,
            'key' => 'memo-'.$memo->id.'-header',
            'url' => 'https://onlyoffice.test/header-file.docx',
        ];
        $token = (new JwtSigner('callback-secret'))->sign([
            'payload' => $body,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('onlyoffice.callback', $memo), $body)
            ->assertOk()
            ->assertJson(['error' => 0]);

        Storage::disk('local')->assertExists($memo->refresh()->file_path);
        $this->assertSame('header-docx', Storage::disk('local')->get($memo->file_path));
    }

    public function test_callback_accepts_onlyoffice_token_in_body_payload_only(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'callback-secret',
            'services.onlyoffice.internal_url' => 'https://onlyoffice.test',
        ]);
        Storage::fake('local');
        Http::fake([
            'https://onlyoffice.test/body-file.docx' => Http::response('body-docx', 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $token = (new JwtSigner('callback-secret'))->sign([
            'status' => 2,
            'key' => 'memo-'.$memo->id.'-body',
            'url' => 'https://onlyoffice.test/body-file.docx',
        ]);

        $this->postJson(route('onlyoffice.callback', $memo), [
            'token' => $token,
        ])->assertOk()
            ->assertJson(['error' => 0]);

        Storage::disk('local')->assertExists($memo->refresh()->file_path);
        $this->assertSame('body-docx', Storage::disk('local')->get($memo->file_path));
    }

    public function test_callback_rejects_mismatch_between_header_payload_and_body(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'callback-secret',
            'services.onlyoffice.internal_url' => 'https://onlyoffice.test',
        ]);
        Http::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $token = (new JwtSigner('callback-secret'))->sign([
            'payload' => [
                'status' => 2,
                'key' => 'memo-'.$memo->id.'-header',
                'url' => 'https://onlyoffice.test/header-file.docx',
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('onlyoffice.callback', $memo), [
                'status' => 2,
                'key' => 'memo-'.$memo->id.'-header',
                'url' => 'https://evil.test/header-file.docx',
            ])->assertForbidden();

        Http::assertNothingSent();
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

    public function test_callback_with_version_id_updates_only_that_version_file(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'callback-secret',
            'services.onlyoffice.internal_url' => 'https://onlyoffice.test',
        ]);
        Storage::fake('local');
        Http::fake([
            'https://onlyoffice.test/version-one.docx' => Http::response('late-version-one-docx', 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $versionOne = $memo->versions()->where('version_number', 1)->firstOrFail();
        $versionTwo = $memo->versions()->create([
            'version_number' => 2,
            'label' => 'Versi 2',
            'file_path' => 'memos/'.$user->id.'/memo-v2.docx',
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [],
            'searchable_text' => 'Versi 2',
        ]);

        Storage::disk('local')->put($versionOne->file_path, 'original-version-one');
        Storage::disk('local')->put($versionTwo->file_path, 'current-version-two');

        $memo->forceFill([
            'file_path' => $versionTwo->file_path,
            'current_version_id' => $versionTwo->id,
        ])->save();

        $key = 'memo-'.$memo->id.'-v'.$versionOne->id.'-123';
        $url = 'https://onlyoffice.test/version-one.docx';
        $token = (new JwtSigner('callback-secret'))->sign([
            'status' => 2,
            'key' => $key,
            'url' => $url,
            'exp' => time() + 60,
        ]);

        $this->postJson(route('onlyoffice.callback', [
            'memo' => $memo,
            'version_id' => $versionOne->id,
        ]), [
            'status' => 2,
            'key' => $key,
            'url' => $url,
            'token' => $token,
        ])->assertOk()
            ->assertJson(['error' => 0]);

        $this->assertSame('late-version-one-docx', Storage::disk('local')->get($versionOne->file_path));
        $this->assertSame('current-version-two', Storage::disk('local')->get($versionTwo->file_path));

        $memo->refresh();
        $this->assertSame($versionTwo->id, $memo->current_version_id);
        $this->assertSame($versionTwo->file_path, $memo->file_path);
        $this->assertSame(Memo::STATUS_EDITED, $versionOne->refresh()->status);
        $this->assertSame(Memo::STATUS_GENERATED, $versionTwo->refresh()->status);
    }

    public function test_legacy_callback_key_version_updates_only_that_version_file(): void
    {
        config([
            'services.onlyoffice.jwt_secret' => 'callback-secret',
            'services.onlyoffice.internal_url' => 'https://onlyoffice.test',
        ]);
        Storage::fake('local');
        Http::fake([
            'https://onlyoffice.test/legacy-version-one.docx' => Http::response('legacy-version-one-docx', 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $versionOne = $memo->versions()->where('version_number', 1)->firstOrFail();
        $versionTwo = $memo->versions()->create([
            'version_number' => 2,
            'label' => 'Versi 2',
            'file_path' => 'memos/'.$user->id.'/memo-v2.docx',
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [],
            'searchable_text' => 'Versi 2',
        ]);

        Storage::disk('local')->put($versionOne->file_path, 'original-version-one');
        Storage::disk('local')->put($versionTwo->file_path, 'current-version-two');

        $memo->forceFill([
            'file_path' => $versionTwo->file_path,
            'current_version_id' => $versionTwo->id,
        ])->save();

        $key = 'memo-'.$memo->id.'-v'.$versionOne->id.'-legacy';
        $url = 'https://onlyoffice.test/legacy-version-one.docx';
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

        $this->assertSame('legacy-version-one-docx', Storage::disk('local')->get($versionOne->file_path));
        $this->assertSame('current-version-two', Storage::disk('local')->get($versionTwo->file_path));

        $memo->refresh();
        $this->assertSame($versionTwo->id, $memo->current_version_id);
        $this->assertSame($versionTwo->file_path, $memo->file_path);
    }

    protected function createMemo(User $user): Memo
    {
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Test',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo.docx',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $version = $memo->versions()->create([
            'version_number' => 1,
            'label' => 'Versi 1',
            'file_path' => $memo->file_path,
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [],
            'searchable_text' => $memo->title,
        ]);

        $memo->forceFill(['current_version_id' => $version->id])->save();

        return $memo->refresh();
    }
}
