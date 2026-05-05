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
        Schema::create('cloud_storage_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->enum('direction', ['import', 'export']);
            $table->morphs('local');
            $table->string('external_id');
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->string('web_view_link')->nullable();
            $table->string('folder_external_id')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'external_id'], 'cloud_storage_files_provider_external_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cloud_storage_files');
    }
};
