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
        Schema::create('user_projects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Propriétaire du projet');

            $table->foreignId('financial_goal_id')
                ->constrained('financial_goals')
                ->onDelete('cascade')
                ->comment('Objectif financier associé');

            $table->string('template_key', 50)
                ->nullable()
                ->comment('Template utilisé');

            $table->string('name', 150)
                ->comment('Nom du projet');

            $table->text('description')
                ->nullable()
                ->comment('Description du projet');

            $table->enum('status', ['planning', 'active', 'completed', 'paused', 'cancelled'])
                ->default('planning')
                ->comment('Statut du projet');

            $table->date('start_date')
                ->nullable()
                ->comment('Date de début');

            $table->date('target_date')
                ->nullable()
                ->comment('Date cible');

            $table->date('completed_date')
                ->nullable()
                ->comment('Date de réalisation');

            $table->json('custom_categories')
                ->nullable()
                ->comment('Catégories personnalisées');

            $table->json('milestones')
                ->nullable()
                ->comment('Étapes du projet');

            $table->json('settings')
                ->nullable()
                ->comment('Paramètres du projet');

            $table->decimal('budget_allocated', 12, 2)
                ->default(0)
                ->comment('Budget alloué');

            $table->decimal('budget_spent', 12, 2)
                ->default(0)
                ->comment('Budget dépensé');

            $table->unsignedTinyInteger('priority')
                ->default(3)
                ->comment('Priorité (1=haute, 5=basse)');

            $table->json('collaborators')
                ->nullable()
                ->comment('Collaborateurs du projet');

            $table->boolean('is_shared')
                ->default(false)
                ->comment('Projet partagé');

            $table->json('notifications_settings')
                ->nullable()
                ->comment('Paramètres de notifications');

            $table->timestamps();
            $table->softDeletes();

            // Index pour optimiser les requêtes
            $table->index(['user_id', 'status'], 'idx_user_projects_user_status');
            $table->index(['template_key'], 'idx_user_projects_template');
            $table->index(['status', 'target_date'], 'idx_user_projects_status_date');
            $table->index(['priority', 'status'], 'idx_user_projects_priority_status');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_projects');
    }
};
