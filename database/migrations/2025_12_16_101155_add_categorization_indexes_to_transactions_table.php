<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour ajouter les index de performance
 * sur la table transactions
 *
 * Gain attendu : 15-50x sur les requêtes de catégorisation
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Index pour catégorisation (WHERE user + non catégorisé)
            if (! $this->indexExists('transactions', 'idx_user_cat_date')) {
                $table->index(
                    ['user_id', 'category_id', 'transaction_date'],
                    'idx_user_cat_date'
                );
            }

            // Index covering pour stats (évite table lookup)
            if (! $this->indexExists('transactions', 'idx_stats_covering')) {
                $table->index(
                    ['user_id', 'type', 'transaction_date', 'amount'],
                    'idx_stats_covering'
                );
            }

            // Index pour recherche par description (historique)
            if (! $this->indexExists('transactions', 'idx_user_type_cat')) {
                $table->index(
                    ['user_id', 'type', 'category_id'],
                    'idx_user_type_cat'
                );
            }

            // Index pour bridge_transaction_id (import Bridge)
            if (! $this->indexExists('transactions', 'idx_user_bridge')) {
                $table->index(
                    ['user_id', 'bridge_transaction_id'],
                    'idx_user_bridge'
                );
            }
        });

        // Optionnel : FULLTEXT pour recherche textuelle avancée
        // Décommenter si besoin de recherche full-text
        /*
        DB::statement('
            ALTER TABLE transactions
            ADD FULLTEXT INDEX ft_description
            (description, merchant_name)
        ');
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_user_cat_date');
            $table->dropIndex('idx_stats_covering');
            $table->dropIndex('idx_user_type_cat');
            $table->dropIndex('idx_user_bridge');
        });

        // Si FULLTEXT a été créé
        // DB::statement('ALTER TABLE transactions DROP INDEX ft_description');
    }

    /**
     * Vérifier si un index existe
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'mysql') {
            $indexes = DB::select("SHOW INDEX FROM {$table}");
            foreach ($indexes as $idx) {
                if ($idx->Key_name === $index) {
                    return true;
                }
            }
        } elseif ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list({$table})");
            foreach ($indexes as $idx) {
                if ($idx->name === $index) {
                    return true;
                }
            }
        } elseif ($driver === 'pgsql') {
            $indexes = DB::select('SELECT indexname FROM pg_indexes WHERE tablename = ?', [$table]);
            foreach ($indexes as $idx) {
                if ($idx->indexname === $index) {
                    return true;
                }
            }
        }

        return false;
    }
};
