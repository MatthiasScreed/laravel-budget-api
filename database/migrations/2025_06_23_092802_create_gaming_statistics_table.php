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
        Schema::create('gaming_statistics', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->enum('period_type', ['daily', 'weekly', 'monthly', 'yearly'])
                ->comment('Type de période');

            $table->date('period_date')
                ->comment('Date de la période');

            // Statistiques XP
            $table->unsignedBigInteger('xp_earned')
                ->default(0)
                ->comment('XP gagné sur la période');

            $table->unsignedInteger('levels_gained')
                ->default(0)
                ->comment('Niveaux gagnés');

            // Statistiques achievements
            $table->unsignedInteger('achievements_unlocked')
                ->default(0)
                ->comment('Succès débloqués');

            // Statistiques streaks
            $table->unsignedInteger('streaks_maintained')
                ->default(0)
                ->comment('Streaks maintenues');

            $table->unsignedInteger('streaks_broken')
                ->default(0)
                ->comment('Streaks cassées');

            // Statistiques challenges
            $table->unsignedInteger('challenges_completed')
                ->default(0)
                ->comment('Défis complétés');

            $table->unsignedInteger('daily_challenges_completed')
                ->default(0)
                ->comment('Défis quotidiens complétés');

            // Statistiques activité
            $table->unsignedInteger('login_days')
                ->default(0)
                ->comment('Jours de connexion');

            $table->unsignedInteger('transactions_created')
                ->default(0)
                ->comment('Transactions créées');

            $table->unsignedInteger('goals_completed')
                ->default(0)
                ->comment('Objectifs complétés');

            // Métadonnées
            $table->json('detailed_stats')
                ->nullable()
                ->comment('Statistiques détaillées');

            $table->timestamps();

            $table->unique(['user_id', 'period_type', 'period_date'], 'idx_gaming_stats_unique');
            $table->index(['period_type', 'period_date']);
            $table->index(['user_id', 'period_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gaming_statistics');
    }
};
