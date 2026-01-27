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
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->onDelete('cascade');

            // Données transaction bancaire
            $table->string('external_id'); // ID unique de la banque
            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->decimal('account_balance_after', 12, 2)->nullable();

            // Informations marchand (si disponibles)
            $table->string('merchant_name')->nullable();
            $table->string('merchant_category')->nullable();

            // Métadonnées de traitement
            $table->json('raw_data'); // Données complètes de l'API
            $table->enum('processing_status', [
                'imported', 'categorized', 'converted', 'ignored', 'duplicate',
            ])->default('imported');

            // IA et catégorisation
            $table->foreignId('suggested_category_id')->nullable()->constrained('categories');
            $table->decimal('confidence_score', 3, 2)->nullable(); // 0.00 à 1.00
            $table->foreignId('converted_transaction_id')->nullable()->constrained('transactions');

            // Timestamps
            $table->timestamp('imported_at');
            $table->timestamp('categorized_at')->nullable();
            $table->timestamps();

            // Index pour performance
            $table->unique(['bank_connection_id', 'external_id']);
            $table->index(['processing_status']);
            $table->index(['transaction_date']);
            $table->index(['merchant_category']);
            $table->index(['suggested_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
