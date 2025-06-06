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
        Schema::create('goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_goal_id')
                ->constrained('financial_goals')
                ->onDelete('cascade')
                ->comment('Référence vers l\'objectif financier');

            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null')
                ->comment('Transaction associée (optionnelle)');

            $table->decimal('amount', 12, 2)
                ->unsigned()
                ->comment('Montant de la contribution');

            $table->date('date')
                ->comment('Date de la contribution');

            $table->text('description')
                ->nullable()
                ->comment('Description ou note sur la contribution');

            $table->boolean('is_automatic')
                ->default(false)
                ->comment('Contribution automatique ou manuelle');

            $table->timestamps();
            $table->softDeletes();
        });

        // Créer les index après la création de la table
        Schema::table('goal_contributions', function (Blueprint $table) {
            $table->index(['financial_goal_id', 'date'], 'idx_goal_contributions_goal_date');
            $table->index(['date'], 'idx_goal_contributions_date');
            $table->index(['is_automatic'], 'idx_goal_contributions_automatic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_contributions');
    }
};
