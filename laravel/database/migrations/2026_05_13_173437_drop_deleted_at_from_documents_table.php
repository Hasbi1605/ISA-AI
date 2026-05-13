<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
     */
    public function up(): void
    {
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
