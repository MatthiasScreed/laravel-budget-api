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
        Schema::create('projections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('financial_goal_id')
                ->constrained('financial_goals')
                ->onDelete('cascade')
                ->comment('Objectif financier concerné');

            $table->enum('type', ['optimistic', 'realistic', 'pessimistic'])
                ->default('realistic')
                ->comment('Type de projection');

            $table->date('projected_date')
                ->comment('Date de projection d\'atteinte de l\'objectif');

            $table->decimal('monthly_saving_required', 10, 2)
                ->unsigned()
                ->nullable()
                ->comment('Épargne mensuelle requise');

            $table->decimal('projected_amount', 12, 2)
                ->unsigned()
                ->nullable()
                ->comment('Montant projeté à la date cible');

            $table->decimal('confidence_score', 3, 2)
                ->default(0.50)
                ->comment('Score de confiance (0-1)');

            $table->json('assumptions')
                ->nullable()
                ->comment('Hypothèses de calcul');

            $table->json('milestones')
                ->nullable()
                ->comment('Étapes intermédiaires projetées');

            $table->text('recommendation')
                ->nullable()
                ->comment('Recommandation basée sur la projection');

            $table->enum('status', ['active', 'outdated', 'archived'])
                ->default('active')
                ->comment('Statut de la projection');

            $table->date('calculated_at')
                ->default(now())
                ->comment('Date de calcul de la projection');

            $table->date('expires_at')
                ->nullable()
                ->comment('Date d\'expiration de la projection');

            $table->json('calculation_data')
                ->nullable()
                ->comment('Données détaillées du calcul');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['financial_goal_id', 'type', 'status'], 'idx_projections_goal_type_status');
            $table->index(['projected_date', 'status'], 'idx_projections_date_status');
            $table->index(['calculated_at', 'expires_at'], 'idx_projections_validity');
            $table->index(['confidence_score'], 'idx_projections_confidence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projections');
    }
};
