<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("DELETE t1 FROM cloud_storage_files t1 INNER JOIN cloud_storage_files t2 WHERE t1.id < t2.id AND t1.provider = t2.provider AND t1.direction = t2.direction AND t1.external_id = t2.external_id AND t1.local_type = t2.local_type AND t1.local_id = t2.local_id");

        Schema::table('cloud_storage_files', function (Blueprint $table) {
            $table->unique(['provider', 'direction', 'external_id', 'local_type', 'local_id'], 'cloud_storage_files_unique_record');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_storage_files', function (Blueprint $table) {
            $table->dropUnique('cloud_storage_files_unique_record');
        });
    }
};
