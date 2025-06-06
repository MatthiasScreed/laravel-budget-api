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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Propriétaire de la transaction');

            $table->foreignId('category_id')
                ->constrained('categories')
                ->onDelete('restrict')
                ->comment('Catégorie de la transaction');

            $table->enum('type', ['income', 'expense'])
                ->comment('Type : revenus ou dépenses');

            $table->decimal('amount', 12, 2)
                ->unsigned()
                ->comment('Montant de la transaction');

            $table->text('description')
                ->nullable()
                ->comment('Description ou note sur la transaction');

            $table->date('transaction_date')
                ->comment('Date de la transaction');

            $table->enum('status', ['pending', 'completed', 'cancelled'])
                ->default('completed')
                ->comment('Statut de la transaction');

            $table->string('reference', 100)
                ->nullable()
                ->comment('Référence externe (numéro de chèque, etc.)');

            $table->string('payment_method', 50)
                ->nullable()
                ->comment('Méthode de paiement');

            $table->json('metadata')
                ->nullable()
                ->comment('Données additionnelles (géolocalisation, etc.)');

            // Champs pour les transactions récurrentes
            $table->boolean('is_recurring')
                ->default(false)
                ->comment('Transaction récurrente');

            $table->enum('recurrence_type', ['daily', 'weekly', 'monthly', 'yearly'])
                ->nullable()
                ->comment('Type de récurrence');

            $table->unsignedSmallInteger('recurrence_interval')
                ->default(1)
                ->comment('Intervalle de récurrence (ex: tous les 2 mois)');

            $table->date('recurrence_end_date')
                ->nullable()
                ->comment('Date de fin de récurrence');

            $table->foreignId('parent_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null')
                ->comment('Transaction parente (pour récurrences)');

            // Champs pour rapprochement bancaire
            $table->boolean('is_reconciled')
                ->default(false)
                ->comment('Transaction rapprochée');

            $table->date('reconciled_at')
                ->nullable()
                ->comment('Date de rapprochement');

            // Champs système
            $table->boolean('is_transfer')
                ->default(false)
                ->comment('Transaction de transfert entre comptes');

            $table->foreignId('transfer_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null')
                ->comment('Transaction de transfert liée');

            $table->string('source', 50)
                ->default('manual')
                ->comment('Source : manual, import, api, etc.');

            $table->timestamps();
            $table->softDeletes();

            // Index pour optimiser les requêtes
            $table->index(['user_id', 'transaction_date'], 'idx_transactions_user_date');
            $table->index(['user_id', 'type', 'status'], 'idx_transactions_user_type_status');
            $table->index(['category_id', 'transaction_date'], 'idx_transactions_category_date');
            $table->index(['is_recurring', 'recurrence_type'], 'idx_transactions_recurring');
            $table->index(['status', 'transaction_date'], 'idx_transactions_status_date');
            $table->index(['is_reconciled'], 'idx_transactions_reconciled');
            $table->index(['parent_transaction_id'], 'idx_transactions_parent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
