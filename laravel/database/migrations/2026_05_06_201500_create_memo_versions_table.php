<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memo_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('label')->nullable();
            $table->string('file_path')->nullable();
            $table->string('status', 40)->default('generated');
            $table->json('configuration')->nullable();
            $table->text('searchable_text')->nullable();
            $table->text('revision_instruction')->nullable();
            $table->timestamps();

            $table->unique(['memo_id', 'version_number']);
            $table->index(['memo_id', 'created_at']);
        });

        Schema::table('memos', function (Blueprint $table) {
            $table->unsignedBigInteger('current_version_id')->nullable()->after('file_path')->index();
        });

        $now = now();

        DB::table('memos')
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($memos) use ($now): void {
                foreach ($memos as $memo) {
                    $versionId = DB::table('memo_versions')->insertGetId([
                        'memo_id' => $memo->id,
                        'version_number' => 1,
                        'label' => 'Versi 1',
                        'file_path' => $memo->file_path,
                        'status' => $memo->status ?: 'generated',
                        'configuration' => $memo->configuration,
                        'searchable_text' => $memo->searchable_text,
                        'revision_instruction' => null,
                        'created_at' => $memo->created_at ?: $now,
                        'updated_at' => $memo->updated_at ?: $now,
                    ]);

                    DB::table('memos')
                        ->where('id', $memo->id)
                        ->update(['current_version_id' => $versionId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('memos', function (Blueprint $table) {
            $table->dropIndex(['current_version_id']);
            $table->dropColumn('current_version_id');
        });

        Schema::dropIfExists('memo_versions');
    }
};
