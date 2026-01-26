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
        Schema::table('bank_connections', function (Blueprint $table) {
            // Ajouter last_error_at si elle n'existe pas
            if (! Schema::hasColumn('bank_connections', 'last_error_at')) {
                $table->timestamp('last_error_at')
                    ->after('last_error')
                    ->nullable()
                    ->comment('Date de la dernière erreur de synchronisation');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_connections', function (Blueprint $table) {
            if (Schema::hasColumn('bank_connections', 'last_error_at')) {
                $table->dropColumn('last_error_at');
            }
        });
    }
};
