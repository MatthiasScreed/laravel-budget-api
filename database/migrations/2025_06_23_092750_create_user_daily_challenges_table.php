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
        Schema::create('user_daily_challenges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('daily_challenge_id')
                ->constrained('daily_challenges')
                ->onDelete('cascade');

            $table->enum('status', ['active', 'completed', 'failed', 'skipped'])
                ->default('active')
                ->comment('Statut du défi');

            $table->decimal('progress_percentage', 5, 2)
                ->default(0)
                ->comment('Progression en %');

            $table->json('progress_data')
                ->nullable()
                ->comment('Données de progression');

            $table->timestamp('started_at')
                ->default(now())
                ->comment('Date de début');

            $table->timestamp('completed_at')
                ->nullable()
                ->comment('Date de completion');

            $table->boolean('reward_claimed')
                ->default(false)
                ->comment('Récompense réclamée');

            $table->timestamps();

            $table->unique(['user_id', 'daily_challenge_id'], 'idx_user_daily_challenge_unique');
            $table->index(['user_id', 'status']);
            $table->index(['daily_challenge_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_daily_challenges');
    }
};
