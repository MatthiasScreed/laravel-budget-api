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
        Schema::create('financial_goals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Propriétaire de l\'objectif');

            $table->string('name', 150)
                ->comment('Nom de l\'objectif financier');

            $table->text('description')
                ->nullable()
                ->comment('Description détaillée de l\'objectif');

            $table->decimal('target_amount', 12, 2)
                ->unsigned()
                ->comment('Montant cible à atteindre');

            $table->decimal('current_amount', 12, 2)
                ->unsigned()
                ->default(0)
                ->comment('Montant actuellement économisé (calculé automatiquement)');

            $table->date('target_date')
                ->nullable()
                ->comment('Date cible pour atteindre l\'objectif');

            $table->date('start_date')
                ->default(now())
                ->comment('Date de début de l\'objectif');

            $table->enum('status', ['active', 'completed', 'paused', 'cancelled'])
                ->default('active')
                ->comment('Statut de l\'objectif');

            $table->enum('type', ['savings', 'debt_payoff', 'investment', 'purchase', 'emergency_fund', 'other'])
                ->default('savings')
                ->comment('Type d\'objectif financier');

            $table->unsignedTinyInteger('priority')
                ->default(3)
                ->comment('Priorité (1=haute, 5=basse)');

            $table->string('color', 7)
                ->default('#3B82F6')
                ->comment('Couleur pour l\'affichage');

            $table->string('icon', 50)
                ->nullable()
                ->comment('Icône associée');

            $table->decimal('monthly_target', 10, 2)
                ->nullable()
                ->unsigned()
                ->comment('Objectif mensuel de contribution');

            $table->boolean('is_automatic')
                ->default(false)
                ->comment('Contributions automatiques activées');

            $table->decimal('automatic_amount', 10, 2)
                ->nullable()
                ->unsigned()
                ->comment('Montant de contribution automatique');

            $table->enum('automatic_frequency', ['weekly', 'monthly', 'quarterly'])
                ->nullable()
                ->comment('Fréquence des contributions automatiques');

            $table->date('next_automatic_date')
                ->nullable()
                ->comment('Prochaine date de contribution automatique');

            $table->json('milestones')
                ->nullable()
                ->comment('Étapes intermédiaires (25%, 50%, 75%, etc.)');

            $table->text('notes')
                ->nullable()
                ->comment('Notes personnelles sur l\'objectif');

            $table->boolean('is_shared')
                ->default(false)
                ->comment('Objectif partagé avec d\'autres utilisateurs');

            $table->json('tags')
                ->nullable()
                ->comment('Tags pour catégoriser l\'objectif');

            $table->date('completed_at')
                ->nullable()
                ->comment('Date d\'achèvement de l\'objectif');

            $table->timestamps();
            $table->softDeletes();

            // Index pour optimiser les requêtes
            $table->index(['user_id', 'status'], 'idx_financial_goals_user_status');
            $table->index(['user_id', 'type', 'status'], 'idx_financial_goals_user_type_status');
            $table->index(['target_date', 'status'], 'idx_financial_goals_target_date_status');
            $table->index(['priority', 'status'], 'idx_financial_goals_priority_status');
            $table->index(['is_automatic', 'next_automatic_date'], 'idx_financial_goals_automatic');
            $table->index(['completed_at'], 'idx_financial_goals_completed');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Désactiver les contraintes de clés étrangères

        Schema::dropIfExists('financial_goals');

        // Réactiver les contraintes de clés étrangères
    }
};
