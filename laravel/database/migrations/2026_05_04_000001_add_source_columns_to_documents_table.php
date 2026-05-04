<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('source_provider', 40)->default('local')->after('file_path');
            $table->string('source_external_id')->nullable()->after('source_provider');
            $table->timestamp('source_synced_at')->nullable()->after('source_external_id');

            $table->index(['source_provider', 'source_external_id'], 'documents_source_provider_external_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_source_provider_external_id_index');
            $table->dropColumn(['source_provider', 'source_external_id', 'source_synced_at']);
        });
    }
};
