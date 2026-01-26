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
        Schema::table('user_sessions_extended', function (Blueprint $table) {

            $table->string('token_id')->nullable()->index()->after('session_id');

            // Colonnes de timing manquantes
            $table->timestamp('last_activity_at')->after('ended_at');
            $table->timestamp('expires_at')->nullable()->after('last_activity_at');

            // Informations d'appareil détaillées
            $table->string('device_name')->nullable()->after('device_type');
            $table->ipAddress('ip_address')->nullable()->after('device_name');
            $table->json('device_info')->nullable()->after('user_agent');

            // Statut de session
            $table->boolean('is_current')->default(true)->after('device_info');

        });

        // Ajouter les index manquants pour les performances
        Schema::table('user_sessions_extended', function (Blueprint $table) {
            $table->index(['user_id', 'is_current'], 'idx_user_sessions_user_current');
            $table->index(['is_current', 'last_activity_at'], 'idx_user_sessions_current_activity');
        });

        // Mettre à jour les données existantes avec des valeurs par défaut
        DB::table('user_sessions_extended')->update([
            'last_activity_at' => DB::raw('COALESCE(ended_at, started_at)'),
            'is_current' => false, // Les sessions existantes sont probablement terminées
            'device_name' => 'Unknown Device',
            'device_info' => json_encode([
                'browser' => 'Unknown',
                'platform' => 'Unknown',
                'device' => 'Unknown',
                'version' => 'Unknown',
            ]),
        ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_sessions_extended', function (Blueprint $table) {
            // Supprimer les index ajoutés
            $table->dropIndex('idx_user_sessions_user_current');
            $table->dropIndex('idx_user_sessions_current_activity');

            // Supprimer les colonnes ajoutées
            $table->dropColumn([
                'token_id',
                'last_activity_at',
                'expires_at',
                'device_name',
                'ip_address',
                'device_info',
                'is_current',
            ]);
        });
    }
};
