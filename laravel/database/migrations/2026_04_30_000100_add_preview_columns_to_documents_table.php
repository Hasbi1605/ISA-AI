<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('preview_html_path', 500)->nullable()->after('file_path');
            $table->enum('preview_status', ['pending', 'ready', 'failed'])
                ->default('pending')
                ->after('preview_html_path');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['preview_html_path', 'preview_status']);
        });
    }
};
