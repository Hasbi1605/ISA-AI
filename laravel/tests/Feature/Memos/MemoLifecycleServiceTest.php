<?php

namespace Tests\Feature\Memos;

use App\Models\Memo;
use App\Models\MemoVersion;
use App\Models\User;
use App\Services\Memo\MemoLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemoLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_memo_removes_all_version_docx_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Test',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
            'file_path' => 'memos/'.$user->id.'/memo-v2.docx',
        ]);

        $v1 = $memo->versions()->create([
            'version_number' => 1,
            'label' => 'Versi 1',
            'file_path' => 'memos/'.$user->id.'/memo-v1.docx',
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [],
        ]);

        $v2 = $memo->versions()->create([
            'version_number' => 2,
            'label' => 'Versi 2',
            'file_path' => 'memos/'.$user->id.'/memo-v2.docx',
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [],
        ]);

        $memo->forceFill(['current_version_id' => $v2->id])->save();

        Storage::disk('local')->put('memos/'.$user->id.'/memo-v1.docx', 'docx-v1');
        Storage::disk('local')->put('memos/'.$user->id.'/memo-v2.docx', 'docx-v2');

        app(MemoLifecycleService::class)->deleteMemo($memo->fresh(['versions']));

        // All DOCX files must be deleted
        Storage::disk('local')->assertMissing('memos/'.$user->id.'/memo-v1.docx');
        Storage::disk('local')->assertMissing('memos/'.$user->id.'/memo-v2.docx');

        // Memo and all versions must be gone from DB
        $this->assertDatabaseMissing('memos', ['id' => $memo->id]);
        $this->assertDatabaseMissing('memo_versions', ['memo_id' => $memo->id]);
    }

    public function test_delete_memo_removes_memo_version_rows(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Versi Test',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
        ]);

        $memo->versions()->create([
            'version_number' => 1,
            'label' => 'Versi 1',
            'file_path' => null,
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [],
        ]);

        $memo->versions()->create([
            'version_number' => 2,
            'label' => 'Versi 2',
            'file_path' => null,
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [],
        ]);

        $this->assertSame(2, MemoVersion::where('memo_id', $memo->id)->count());

        app(MemoLifecycleService::class)->deleteMemo($memo->fresh(['versions']));

        // No orphan version rows
        $this->assertSame(0, MemoVersion::where('memo_id', $memo->id)->count());
        $this->assertDatabaseMissing('memos', ['id' => $memo->id]);
    }

    public function test_delete_memo_tolerates_missing_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Missing File',
            'memo_type' => 'memo_internal',
            'status' => Memo::STATUS_GENERATED,
            'file_path' => 'memos/'.$user->id.'/nonexistent.docx',
        ]);

        $memo->versions()->create([
            'version_number' => 1,
            'label' => 'Versi 1',
            'file_path' => 'memos/'.$user->id.'/also-nonexistent.docx',
            'status' => Memo::STATUS_GENERATED,
            'configuration' => [],
        ]);

        // Should not throw even if files don't exist
        app(MemoLifecycleService::class)->deleteMemo($memo->fresh(['versions']));

        $this->assertDatabaseMissing('memos', ['id' => $memo->id]);
    }
}
