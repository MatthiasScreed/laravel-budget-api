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
        Schema::create('user_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('challenge_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['active', 'completed', 'failed', 'abandoned'])->default('active');
            $table->decimal('progress_percentage', 5, 2)->default(0)->comment('Progression en %');
            $table->json('progress_data')->nullable()->comment('Données de progression');
            $table->timestamp('started_at')->default(now())->comment('Date de début');
            $table->timestamp('completed_at')->nullable()->comment('Date de fin');
            $table->timestamps();

            $table->unique(['user_id', 'challenge_id']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_challenges');
    }
};
