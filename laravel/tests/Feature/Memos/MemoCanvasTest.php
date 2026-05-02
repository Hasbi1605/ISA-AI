<?php

namespace Tests\Feature\Memos;

use App\Livewire\Memos\MemoCanvas;
use App\Models\Memo;
use App\Models\User;
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
}
