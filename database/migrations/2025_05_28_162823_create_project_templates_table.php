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
        Schema::create('project_templates', function (Blueprint $table) {
            $table->id();

            $table->string('key', 50)
                ->unique()
                ->comment('Clé unique du template');

            $table->string('name', 100)
                ->comment('Nom du template');

            $table->text('description')
                ->comment('Description du template');

            $table->string('icon', 50)
                ->nullable()
                ->comment('Icône du template');

            $table->string('color', 7)
                ->default('#3B82F6')
                ->comment('Couleur du template');

            $table->enum('type', ['savings', 'debt_payoff', 'investment', 'purchase', 'emergency_fund', 'other'])
                ->default('savings')
                ->comment('Type d\'objectif financier');

            $table->json('categories')
                ->comment('Catégories par défaut avec pourcentages');

            $table->unsignedSmallInteger('default_duration_months')
                ->default(12)
                ->comment('Durée par défaut en mois');

            $table->json('tips')
                ->nullable()
                ->comment('Conseils pour ce type de projet');

            $table->json('milestones')
                ->nullable()
                ->comment('Étapes prédéfinies');

            $table->decimal('min_amount', 10, 2)
                ->nullable()
                ->comment('Montant minimum recommandé');

            $table->decimal('max_amount', 12, 2)
                ->nullable()
                ->comment('Montant maximum recommandé');

            $table->unsignedInteger('popularity_score')
                ->default(0)
                ->comment('Score de popularité');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Template actif');

            $table->boolean('is_premium')
                ->default(false)
                ->comment('Template réservé aux comptes premium');

            $table->json('metadata')
                ->nullable()
                ->comment('Métadonnées additionnelles');

            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['is_active', 'popularity_score'], 'idx_templates_active_popular');
            $table->index(['type', 'is_active'], 'idx_templates_type_active');
            $table->index(['is_premium'], 'idx_templates_premium');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_templates');
    }
};
