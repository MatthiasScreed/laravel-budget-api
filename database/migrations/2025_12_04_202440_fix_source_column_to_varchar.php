<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix le champ 'source' en le passant de ENUM à VARCHAR
     * pour permettre plus de flexibilité (Bridge API, etc.)
     */
    public function up(): void
    {
        // ✅ Convertir ENUM en VARCHAR pour flexibilité
        DB::statement(
            "ALTER TABLE transactions
            MODIFY COLUMN source VARCHAR(50)
            DEFAULT 'manual'
            COMMENT 'Source de la transaction : manual, bridge, bank_import, api, recurring, etc.'"
        );

        // ✅ Nettoyer les valeurs invalides si nécessaire
        DB::table('transactions')
            ->whereNotIn('source', [
                'manual',
                'bridge',
                'bank_import',
                'api',
                'recurring',
            ])
            ->update(['source' => 'manual']);
    }

    /**
     * Revenir à l'ENUM si nécessaire (déconseillé)
     */
    public function down(): void
    {
        DB::statement(
            "ALTER TABLE transactions
            MODIFY COLUMN source ENUM(
                'manual',
                'bridge',
                'bank_import',
                'api',
                'recurring'
            ) DEFAULT 'manual'"
        );
    }
};
