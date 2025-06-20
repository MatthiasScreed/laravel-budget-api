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
        // Vérifier si la colonne existe déjà
        if (Schema::hasTable('streaks') && !Schema::hasColumn('streaks', 'bonus_claimed_at')) {
            Schema::table('streaks', function (Blueprint $table) {
                $table->timestamp('bonus_claimed_at')->nullable()->after('is_active');
            });
        }

        // Si la table existe mais que le type n'est pas enum, on la modifie
        if (Schema::hasTable('streaks')) {
            // Recreer la table avec les bonnes contraintes
            Schema::table('streaks', function (Blueprint $table) {
                // Supprimer l'ancien index si il existe
                $table->dropIndex(['type']);
            });

            Schema::table('streaks', function (Blueprint $table) {
                // Modifier la colonne type pour accepter les bonnes valeurs
                $table->string('type')->change();
                $table->index('type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('streaks', 'bonus_claimed_at')) {
            Schema::table('streaks', function (Blueprint $table) {
                $table->dropColumn('bonus_claimed_at');
            });
        }
    }
};
