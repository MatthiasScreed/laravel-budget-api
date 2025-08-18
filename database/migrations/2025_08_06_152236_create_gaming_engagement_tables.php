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
        // Table pour tracker toutes les micro-actions
        Schema::create('user_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 50); // 'page_view', 'click', 'transaction_add', etc.
            $table->string('context', 100)->nullable(); // Page/section où l'action s'est passée
            $table->json('metadata')->nullable(); // Données additionnelles
            $table->tinyInteger('xp_gained')->default(1); // XP gagné pour cette action
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            // Index pour performance
            $table->index(['user_id', 'created_at']);
            $table->index(['action_type', 'created_at']);
        });

        // Table pour les notifications push temps réel
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // 'xp_gain', 'achievement', 'level_up', 'streak', etc.
            $table->string('title', 150);
            $table->text('body');
            $table->json('data')->nullable(); // Données pour l'action
            $table->string('channel', 20)->default('web'); // 'web', 'push', 'email'
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            // Index pour queries fréquentes
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type', 'created_at']);
        });

        // Table pour les événements temporaires (FOMO)
        Schema::create('gaming_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 30); // 'double_xp', 'bonus_achievement', 'challenge'
            $table->text('description')->nullable();
            $table->decimal('multiplier', 5, 2)->default(1.00); // Multiplicateur XP
            $table->json('conditions')->nullable(); // Conditions d'activation
            $table->json('rewards')->nullable(); // Récompenses spéciales
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Index pour événements actifs
            $table->index(['is_active', 'start_at', 'end_at']);
        });

        // Table pour les ligues/classements
        Schema::create('user_leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('league_type', 30)->default('global'); // 'global', 'weekly', 'monthly'
            $table->string('league_tier', 20)->default('bronze'); // 'bronze', 'silver', 'gold', etc.
            $table->integer('current_score')->default(0); // Score pour cette période
            $table->integer('rank_position')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            // Index pour classements
            $table->index(['league_type', 'league_tier', 'current_score']);
            $table->index(['period_start', 'period_end']);
        });

        // Table pour les sessions utilisateur (engagement tracking)
        Schema::create('user_sessions_extended', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 100);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('actions_count')->default(0);
            $table->integer('xp_earned')->default(0);
            $table->json('pages_visited')->nullable();
            $table->string('device_type', 20)->nullable(); // 'mobile', 'desktop', 'tablet'
            $table->string('user_agent', 255)->nullable();

            // Index pour analytics
            $table->index(['user_id', 'started_at']);
            $table->index(['session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions_extended');
        Schema::dropIfExists('user_leagues');
        Schema::dropIfExists('gaming_events');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('user_actions');
    }
};
