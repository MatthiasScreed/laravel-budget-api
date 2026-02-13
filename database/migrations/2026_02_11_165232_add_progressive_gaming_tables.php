<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * TABLES AJOUTÉES (n'existent pas encore) :
     * - user_gaming_profiles (profil adaptatif)
     * - milestones (paliers financiers)
     * - user_milestones (progression paliers)
     * - feedback_templates (messages contextuels)
     * - user_feedback_log (historique feedbacks)
     *
     * NOTE: gaming_engagement_events → on utilise gaming_events existante
     */
    public function up(): void
    {
        // ==========================================
        // PROFIL GAMING ADAPTATIF
        // ==========================================
        if (!Schema::hasTable('user_gaming_profiles')) {
            Schema::create('user_gaming_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');

                // Niveau d'engagement gaming (1-4)
                $table->tinyInteger('engagement_level')->default(1);
                $table->tinyInteger('gaming_affinity_score')->default(50);
                $table->enum('gaming_preference', ['auto', 'minimal', 'moderate', 'full'])
                    ->default('auto');

                // Métriques
                $table->integer('total_interactions')->default(0);
                $table->integer('gaming_interactions')->default(0);
                $table->integer('dismissed_gaming_elements')->default(0);
                $table->timestamp('last_gaming_interaction')->nullable();

                // Features & Settings
                $table->json('unlocked_features')->nullable();
                $table->boolean('show_xp_notifications')->default(false);
                $table->boolean('show_level_badges')->default(false);
                $table->boolean('show_leaderboard')->default(false);
                $table->boolean('show_challenges')->default(false);

                $table->timestamps();

                $table->unique('user_id');
                // ✅ Nom court explicite pour éviter l'erreur MySQL
                $table->index(['engagement_level', 'gaming_affinity_score'], 'ugp_engagement_affinity_idx');
            });
        }

        // ==========================================
        // MILESTONES (Paliers)
        // ==========================================
        if (!Schema::hasTable('milestones')) {
            Schema::create('milestones', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('category', 30);
                $table->string('title');
                $table->string('description');
                $table->string('icon', 10)->default('✓');
                $table->json('conditions');
                $table->integer('points_reward')->default(0);
                $table->string('feature_unlock')->nullable();
                $table->json('rewards')->nullable();
                $table->tinyInteger('min_engagement_level')->default(1);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['category', 'is_active', 'sort_order'], 'ms_category_active_idx');
            });
        }

        // ==========================================
        // USER MILESTONES
        // ==========================================
        if (!Schema::hasTable('user_milestones')) {
            Schema::create('user_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('milestone_id')->constrained()->onDelete('cascade');
                $table->decimal('progress', 10, 2)->default(0);
                $table->decimal('target', 10, 2);
                $table->boolean('is_completed')->default(false);
                $table->timestamp('completed_at')->nullable();
                $table->boolean('reward_claimed')->default(false);
                $table->json('completion_context')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'milestone_id']);
                $table->index(['user_id', 'is_completed'], 'um_user_completed_idx');

            });
        }

        // ==========================================
        // FEEDBACK TEMPLATES
        // ==========================================
        if (!Schema::hasTable('feedback_templates')) {
            Schema::create('feedback_templates', function (Blueprint $table) {
                $table->id();
                $table->string('trigger_event', 50);
                $table->string('category', 30);
                $table->string('title');
                $table->text('message');
                $table->string('icon', 10)->default('💡');
                $table->json('conditions')->nullable();
                $table->tinyInteger('engagement_level')->default(1);
                $table->enum('tone', ['neutral', 'encouraging', 'celebratory', 'informative'])
                    ->default('encouraging');
                $table->tinyInteger('priority')->default(5);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['trigger_event', 'engagement_level', 'is_active'], 'ft_trigger_engagement_idx');
            });
        }

        // ==========================================
        // FEEDBACK LOG
        // ==========================================
        if (!Schema::hasTable('user_feedback_log')) {
            Schema::create('user_feedback_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('feedback_template_id')->constrained()->onDelete('cascade');
                $table->string('trigger_event', 50);
                $table->json('context')->nullable();
                $table->enum('user_reaction', ['seen', 'clicked', 'dismissed', 'ignored'])
                    ->nullable();
                $table->timestamp('reacted_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'trigger_event', 'created_at'], 'ufl_user_trigger_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_feedback_log');
        Schema::dropIfExists('feedback_templates');
        Schema::dropIfExists('user_milestones');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('user_gaming_profiles');
    }
};
