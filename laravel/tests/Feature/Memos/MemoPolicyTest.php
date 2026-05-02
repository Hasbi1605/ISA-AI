<?php

namespace Tests\Feature\Memos;

use App\Models\Memo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MemoPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_download_memo_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        Storage::disk('local')->put($memo->file_path, 'docx-bytes');

        $this->actingAs($user)
            ->get(route('memos.download', $memo))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_non_owner_cannot_download_memo_file(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($owner);
        Storage::disk('local')->put($memo->file_path, 'docx-bytes');

        $this->actingAs($other)
            ->get(route('memos.download', $memo))
            ->assertForbidden();
    }

    public function test_signed_file_route_accepts_relative_signature_for_onlyoffice(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        Storage::disk('local')->put($memo->file_path, 'docx-bytes');

        $path = URL::temporarySignedRoute('memos.file.signed', now()->addHour(), $memo, false);

        $this->get($path)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
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
