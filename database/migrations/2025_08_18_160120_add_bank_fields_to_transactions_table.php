<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // ✅ Vérifier et ajouter bank_connection_id seulement si inexistante
            if (! Schema::hasColumn('transactions', 'bank_connection_id')) {
                $table->foreignId('bank_connection_id')->nullable()->constrained('bank_connections')->onDelete('set null');
            }

            // ✅ Vérifier et ajouter external_transaction_id seulement si inexistante
            if (! Schema::hasColumn('transactions', 'external_transaction_id')) {
                $table->string('external_transaction_id')->nullable();
            }
        });

        // ✅ Gestion séparée de la colonne source (éviter conflits dans Schema::table)
        if (! Schema::hasColumn('transactions', 'source')) {
            DB::statement("ALTER TABLE transactions ADD COLUMN source ENUM('manual', 'bank_import', 'api', 'recurring') DEFAULT 'manual'");
        } else {
            // Modifier les valeurs ENUM existantes pour inclure les nouvelles options
            try {
                DB::statement("ALTER TABLE transactions MODIFY COLUMN source ENUM('manual', 'bank_import', 'api', 'recurring') DEFAULT 'manual'");
            } catch (\Exception $e) {
                // Si ça échoue, on continue (peut-être que les valeurs existent déjà)
                \Log::info('Source column modification skipped: '.$e->getMessage());
            }
        }

        // ✅ Ajouter les index seulement s'ils n'existent pas
        $this->addIndexIfNotExists('transactions', 'bank_connection_id');
        $this->addIndexIfNotExists('transactions', 'external_transaction_id');
        $this->addIndexIfNotExists('transactions', 'source');
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Supprimer seulement les colonnes qu'on a ajoutées
            if (Schema::hasColumn('transactions', 'bank_connection_id')) {
                $table->dropForeign(['bank_connection_id']);
                $table->dropColumn('bank_connection_id');
            }

            if (Schema::hasColumn('transactions', 'external_transaction_id')) {
                $table->dropColumn('external_transaction_id');
            }

            // Note: On ne touche pas à 'source' car elle était peut-être déjà là
        });
    }

    /**
     * Ajouter un index seulement s'il n'existe pas
     */
    private function addIndexIfNotExists(string $table, string $column): void
    {
        try {
            $indexName = "idx_{$table}_{$column}";

            // Vérifier si l'index existe
            $indexes = collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]));

            if ($indexes->isEmpty()) {
                DB::statement("CREATE INDEX {$indexName} ON {$table} ({$column})");
                \Log::info("Index créé: {$indexName}");
            } else {
                \Log::info("Index existe déjà: {$indexName}");
            }
        } catch (\Exception $e) {
            \Log::warning("Impossible de créer l'index {$column}: ".$e->getMessage());
        }
    }
};
