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
        Schema::create('leaderboards', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)
                ->comment('Type de classement (xp, streak, achievements, etc.)');

            $table->string('period', 20)
                ->default('all_time')
                ->comment('Période (daily, weekly, monthly, all_time)');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('username', 100)
                ->comment('Cache du nom utilisateur');

            $table->unsignedBigInteger('score')
                ->default(0)
                ->comment('Score pour ce classement');

            $table->unsignedInteger('rank')
                ->comment('Position dans le classement');

            $table->json('metadata')
                ->nullable()
                ->comment('Données additionnelles');

            $table->date('period_date')
                ->comment('Date de la période');
            $table->timestamps();

            // Index pour performance
            $table->unique(['type', 'period', 'user_id', 'period_date'], 'idx_leaderboard_unique');
            $table->index(['type', 'period', 'rank', 'period_date'], 'idx_leaderboard_ranking');
            $table->index(['user_id', 'type'], 'idx_leaderboard_user_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboards');
    }
};
