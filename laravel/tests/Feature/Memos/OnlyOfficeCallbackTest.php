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
            'url' => 'https://onlyoffice.test/file.docx',
        ])->assertUnauthorized();
    }

    public function test_callback_with_valid_token_saves_file(): void
    {
        config(['services.onlyoffice.jwt_secret' => 'callback-secret']);
        Storage::fake('local');
        Http::fake([
            'https://onlyoffice.test/file.docx' => Http::response('updated-docx', 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $token = (new JwtSigner('callback-secret'))->sign([
            'memo_id' => $memo->id,
            'exp' => time() + 60,
        ]);

        $this->postJson(route('onlyoffice.callback', $memo), [
            'status' => 2,
            'url' => 'https://onlyoffice.test/file.docx',
            'token' => $token,
        ])->assertOk()
            ->assertJson(['error' => 0]);

        $memo->refresh();
        $this->assertSame(Memo::STATUS_EDITED, $memo->status);
        Storage::disk('local')->assertExists($memo->file_path);
        $this->assertSame('updated-docx', Storage::disk('local')->get($memo->file_path));
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
