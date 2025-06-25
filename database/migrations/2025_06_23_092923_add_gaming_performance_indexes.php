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
        Schema::table('achievements', function (Blueprint $table) {
            $table->index(['type', 'rarity', 'is_active'], 'idx_achievements_type_rarity_active');
            $table->index(['points', 'rarity'], 'idx_achievements_points_rarity');
        });

        // Table user_achievements - Optimiser les statistiques
        Schema::table('user_achievements', function (Blueprint $table) {
            $table->index(['achievement_id', 'unlocked_at'], 'idx_user_achievements_achievement_date');
        });

        // Table streaks - Optimiser les leaderboards
        Schema::table('streaks', function (Blueprint $table) {
            $table->index(['type', 'best_count', 'is_active'], 'idx_streaks_type_best_active');
            $table->index(['is_active', 'last_activity_date'], 'idx_streaks_active_activity');
        });

        // Table user_levels - Optimiser les classements
        Schema::table('user_levels', function (Blueprint $table) {
            $table->index(['total_xp', 'level'], 'idx_user_levels_xp_level');
        });

        // Table transactions - Optimiser les requêtes gaming
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'created_at', 'status'], 'idx_transactions_user_created_status');
        });

        // Table financial_goals - Optimiser les statistiques
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->index(['user_id', 'completed_at'], 'idx_financial_goals_user_completed');
            $table->index(['status', 'completed_at'], 'idx_financial_goals_status_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropIndex('idx_achievements_type_rarity_active');
            $table->dropIndex('idx_achievements_points_rarity');
        });

        Schema::table('user_achievements', function (Blueprint $table) {
            $table->dropIndex('idx_user_achievements_achievement_date');
        });

        Schema::table('streaks', function (Blueprint $table) {
            $table->dropIndex('idx_streaks_type_best_active');
            $table->dropIndex('idx_streaks_active_activity');
        });

        Schema::table('user_levels', function (Blueprint $table) {
            $table->dropIndex('idx_user_levels_xp_level');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_user_created_status');
        });

        Schema::table('financial_goals', function (Blueprint $table) {
            $table->dropIndex('idx_financial_goals_user_completed');
            $table->dropIndex('idx_financial_goals_status_completed');
        });
    }
};
