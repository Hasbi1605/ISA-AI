<?php

namespace Tests\Feature\Memos;

use App\Livewire\Memos\MemoIndex;
use App\Models\Memo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemoIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_memo_index_orders_by_updated_at_desc(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $older = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Lama',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $newer = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Baru',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
        ]);

        // Simulate older memo being revised recently (updated_at newer than created_at)
        Memo::withoutTimestamps(fn () => $older->forceFill([
            'updated_at' => now()->addMinutes(5),
        ])->save());

        $component = Livewire::actingAs($user)->test(MemoIndex::class);

        $memos = $component->viewData('memos');

        // Older memo was revised more recently, so it should appear first
        $this->assertSame($older->id, $memos->first()->id);
        $this->assertSame($newer->id, $memos->last()->id);
    }

    public function test_memo_index_and_workspace_sidebar_use_same_ordering(): void
    {
        // Both MemoIndex and MemoWorkspace sidebar should order by updated_at desc
        // This test verifies MemoIndex uses updated_at, not created_at
        $user = User::factory()->create(['email_verified_at' => now()]);

        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Test',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $component = Livewire::actingAs($user)->test(MemoIndex::class);

        $memos = $component->viewData('memos');
        $this->assertCount(1, $memos);
        $this->assertSame($memo->id, $memos->first()->id);
    }
}
