<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the soft-delete column from documents.
     *
     * Documents now use hard delete (Opsi B from issue #159).
     * The previous soft-delete contract was misleading because
     * DocumentLifecycleService already deleted physical files and
     * vector embeddings before calling $document->delete(), making
     * restore impossible anyway.
     *
     * IMPORTANT: Before dropping the column, purge any rows that were
     * previously soft-deleted. Without this step, those rows would
     * reappear as active documents after the column is dropped, even
     * though their physical files, embeddings, and previews are gone.
     */
    public function up(): void
    {
        // Purge previously soft-deleted document rows so they do not
        // resurface as active documents after deleted_at is dropped.
        if (Schema::hasColumn('documents', 'deleted_at')) {
            DB::table('documents')
                ->whereNotNull('deleted_at')
                ->delete();
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
