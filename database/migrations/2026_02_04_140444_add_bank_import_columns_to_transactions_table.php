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
        Schema::table('transactions', function (Blueprint $table) {
            // ID externe de Bridge (pour éviter les doublons)
            if (!Schema::hasColumn('transactions', 'external_id')) {
                $table->string('external_id')->nullable()->after('id');
                $table->index('external_id');
            }

            // Source de la transaction
            if (!Schema::hasColumn('transactions', 'source')) {
                $table->enum('source', ['manual', 'bank_import', 'recurring', 'api'])
                    ->default('manual')
                    ->after('status');
            }

            // Métadonnées JSON pour infos supplémentaires
            if (!Schema::hasColumn('transactions', 'metadata')) {
                $table->json('metadata')->nullable()->after('source');
            }

            // Index unique pour éviter doublons d'import
            // (user_id + external_id doit être unique)
        });

        // Ajouter l'index unique séparément pour éviter erreurs si existe déjà
        try {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unique(['user_id', 'external_id'], 'transactions_user_external_unique');
            });
        } catch (\Exception $e) {
            // Index existe déjà, ignorer
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Supprimer l'index unique d'abord
            try {
                $table->dropUnique('transactions_user_external_unique');
            } catch (\Exception $e) {
                // Ignorer si n'existe pas
            }

            // Supprimer les colonnes
            $columns = ['external_id', 'source', 'metadata'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
