<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('memo_type', 60);
            $table->string('file_path')->nullable();
            $table->string('status', 40)->default('draft');
            $table->foreignId('source_conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->json('source_document_ids')->nullable();
            $table->text('searchable_text')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memos');
    }
};
