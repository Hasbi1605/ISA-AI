<?php

namespace App\Services\Memo;

use App\Models\Memo;
use App\Models\MemoVersion;
use Illuminate\Support\Facades\Storage;

class MemoLifecycleService
{
    /**
     * Delete a memo and clean up all associated DOCX files and cloud storage records.
     *
     * MemoVersion rows are cascade-deleted by the DB foreign key when the memo
     * is force-deleted. For soft-delete we force-delete so the cascade fires and
     * no orphan version rows remain.
     */
    public function deleteMemo(Memo $memo): void
    {
        // 1. Delete all version DOCX files from disk
        foreach ($memo->versions as $version) {
            $this->deleteFile($version->file_path);
        }

        // 2. Delete the master memo file (may differ from current version path)
        $this->deleteFile($memo->file_path);

        // 3. Delete cloud storage records (exports/imports linked to this memo)
        $memo->cloudStorageFiles()->delete();

        // 4. Force-delete the memo so the DB cascade removes MemoVersion rows.
        // Using forceDelete() instead of delete() ensures no orphan version rows
        // remain even if the model uses SoftDeletes.
        $memo->forceDelete();
    }

    protected function deleteFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        try {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Throwable $e) {
            logger()->warning('MemoLifecycleService: failed to delete file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
