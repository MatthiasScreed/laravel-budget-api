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
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token_id')->unique(); // Référence au personal_access_token
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->json('device_info')->nullable(); // OS, Browser, etc.
            $table->timestamp('last_activity_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'last_activity_at']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
