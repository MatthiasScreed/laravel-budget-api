<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();

            // Relation avec la connexion bancaire
            $table->foreignId('bank_connection_id')
                ->constrained('bank_connections')
                ->onDelete('cascade')
                ->comment('Connexion bancaire parente');

            // Identifiants
            $table->string('external_id')
                ->nullable()
                ->comment('ID du compte dans l\'API Bridge');

            $table->string('account_name')
                ->comment('Nom du compte (ex: Compte Courant)');

            // Type de compte
            $table->enum('account_type', [
                'checking',    // Compte courant
                'savings',     // Livret/Épargne
                'credit',      // Carte de crédit
                'investment',  // Compte d\'investissement
                'loan',         // Prêt
            ])->comment('Type de compte bancaire');

            // Informations financières
            $table->decimal('balance', 15, 2)
                ->default(0)
                ->comment('Solde actuel du compte');

            $table->string('currency', 3)
                ->default('EUR')
                ->comment('Devise du compte');

            $table->string('iban', 34)
                ->nullable()
                ->comment('IBAN du compte');

            $table->string('account_number', 50)
                ->nullable()
                ->comment('Numéro de compte (masqué)');

            // Statut
            $table->boolean('is_active')
                ->default(true)
                ->comment('Compte actif ou fermé');

            $table->timestamp('last_balance_update')
                ->nullable()
                ->comment('Dernière mise à jour du solde');

            // Métadonnées Bridge
            $table->json('metadata')
                ->nullable()
                ->comment('Données additionnelles de Bridge');

            $table->timestamps();
            $table->softDeletes();

            // Index pour performance
            $table->index(['bank_connection_id', 'is_active']);
            $table->index(['account_type', 'is_active']);
            $table->unique(['bank_connection_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
