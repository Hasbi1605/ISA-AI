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
        Schema::create('google_drive_oauth_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique()->default('google_drive');
            $table->string('account_email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token');
            $table->string('token_type')->nullable();
            $table->text('scope')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_drive_oauth_connections');
    }
};
