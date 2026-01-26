<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_connections', function (Blueprint $table) {
            // ✅ Ajouter provider_connection_id
            if (! Schema::hasColumn('bank_connections', 'provider_connection_id')) {
                $table->string('provider_connection_id')
                    ->after('provider')
                    ->nullable()
                    ->comment('ID de la connexion chez le provider (Bridge item_id, etc.)');
            }

            // ✅ Ajouter is_active
            if (! Schema::hasColumn('bank_connections', 'is_active')) {
                $table->boolean('is_active')
                    ->after('status')
                    ->default(true)
                    ->comment('Connexion active ou non');
            }

            // ✅ Ajouter last_successful_sync_at
            if (! Schema::hasColumn('bank_connections', 'last_successful_sync_at')) {
                $table->timestamp('last_successful_sync_at')
                    ->after('last_sync_at')
                    ->nullable()
                    ->comment('Dernière synchronisation réussie');
            }

            // ✅ Ajouter metadata
            if (! Schema::hasColumn('bank_connections', 'metadata')) {
                $table->json('metadata')
                    ->after('last_successful_sync_at')
                    ->nullable()
                    ->comment('Métadonnées supplémentaires (JSON)');
            }
        });

        // ✅ Modifier les colonnes existantes APRÈS avoir créé la table
        Schema::table('bank_connections', function (Blueprint $table) {
            // Rendre connection_id nullable
            if (Schema::hasColumn('bank_connections', 'connection_id')) {
                DB::statement('ALTER TABLE bank_connections MODIFY connection_id VARCHAR(255) NULL');
            }

            // Rendre access_token_encrypted nullable
            if (Schema::hasColumn('bank_connections', 'access_token_encrypted')) {
                DB::statement('ALTER TABLE bank_connections MODIFY access_token_encrypted TEXT NULL');
            }
        });

        // ✅ Ajouter l'index UNIQUEMENT s'il n'existe pas
        $indexName = 'bank_connections_provider_connection_id_index';
        $indexes = DB::select('SHOW INDEX FROM bank_connections WHERE Key_name = ?', [$indexName]);

        if (empty($indexes)) {
            Schema::table('bank_connections', function (Blueprint $table) {
                $table->index('provider_connection_id', 'bank_connections_provider_connection_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer l'index d'abord
        $indexName = 'bank_connections_provider_connection_id_index';
        $indexes = DB::select('SHOW INDEX FROM bank_connections WHERE Key_name = ?', [$indexName]);

        if (! empty($indexes)) {
            Schema::table('bank_connections', function (Blueprint $table) {
                $table->dropIndex('bank_connections_provider_connection_id_index');
            });
        }

        // Ensuite supprimer les colonnes
        Schema::table('bank_connections', function (Blueprint $table) {
            if (Schema::hasColumn('bank_connections', 'provider_connection_id')) {
                $table->dropColumn('provider_connection_id');
            }

            if (Schema::hasColumn('bank_connections', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('bank_connections', 'last_successful_sync_at')) {
                $table->dropColumn('last_successful_sync_at');
            }

            if (Schema::hasColumn('bank_connections', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
