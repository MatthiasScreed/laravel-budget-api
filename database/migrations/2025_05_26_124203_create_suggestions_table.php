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
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Utilisateur concerné');

            $table->enum('type', [
                'reduce_expense',
                'increase_income',
                'optimize_savings',
                'category_rebalance',
                'goal_adjustment',
                'budget_alert',
                'investment_opportunity',
                'debt_optimization'
            ])->comment('Type de suggestion');

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])
                ->default('medium')
                ->comment('Priorité de la suggestion');

            $table->string('title', 200)
                ->comment('Titre de la suggestion');

            $table->text('message')
                ->comment('Message détaillé de la suggestion');

            $table->json('action_data')
                ->nullable()
                ->comment('Données pour l\'action suggérée');

            $table->decimal('potential_impact', 10, 2)
                ->nullable()
                ->comment('Impact financier potentiel');

            $table->enum('impact_type', ['savings', 'income', 'goal_achievement'])
                ->nullable()
                ->comment('Type d\'impact');

            $table->foreignId('financial_goal_id')
                ->nullable()
                ->constrained('financial_goals')
                ->onDelete('cascade')
                ->comment('Objectif financier lié');

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->onDelete('set null')
                ->comment('Catégorie liée');

            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null')
                ->comment('Transaction déclencheur');

            $table->enum('status', ['pending', 'seen', 'acted', 'dismissed', 'expired'])
                ->default('pending')
                ->comment('Statut de la suggestion');

            $table->timestamp('seen_at')
                ->nullable()
                ->comment('Date de lecture');

            $table->timestamp('acted_at')
                ->nullable()
                ->comment('Date d\'action');

            $table->timestamp('dismissed_at')
                ->nullable()
                ->comment('Date de rejet');

            $table->date('expires_at')
                ->nullable()
                ->comment('Date d\'expiration');

            $table->json('feedback')
                ->nullable()
                ->comment('Retour utilisateur sur la suggestion');

            $table->decimal('confidence_score', 3, 2)
                ->default(0.50)
                ->comment('Score de confiance de l\'IA');

            $table->string('source', 50)
                ->default('system')
                ->comment('Source de la suggestion');

            $table->json('calculation_basis')
                ->nullable()
                ->comment('Base de calcul de la suggestion');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'priority'], 'idx_suggestions_user_status_priority');
            $table->index(['user_id', 'type', 'status'], 'idx_suggestions_user_type_status');
            $table->index(['financial_goal_id', 'status'], 'idx_suggestions_goal_status');
            $table->index(['expires_at', 'status'], 'idx_suggestions_expiry');
            $table->index(['confidence_score', 'priority'], 'idx_suggestions_confidence_priority');
            $table->index(['created_at', 'status'], 'idx_suggestions_created_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
