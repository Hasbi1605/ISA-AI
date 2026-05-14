<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate (user_id, original_name) records keeping the newest
        // (highest id) before adding the unique constraint.
        // Without this step the migration fails on any environment where the
        // application-level duplicate check was bypassed — matching the same
        // dedup pattern used in the cloud_storage_files migration.
        DB::statement(
            'DELETE d1 FROM documents d1
             INNER JOIN documents d2
             ON d1.user_id = d2.user_id
             AND d1.original_name = d2.original_name
             AND d1.id < d2.id'
        );

        Schema::table('documents', function (Blueprint $table) {
            $table->unique(['user_id', 'original_name'], 'documents_user_id_original_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('documents_user_id_original_name_unique');
        });
    }
};
