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
        Schema::table('users', function (Blueprint $table) {
            $table->json('gaming_preferences')
                ->nullable()
                ->after('preferences')
                ->comment('Préférences gaming (notifications, affichage, etc.)');

            $table->boolean('gaming_enabled')
                ->default(true)
                ->after('gaming_preferences')
                ->comment('Système gaming activé');

            $table->enum('gaming_level_visibility', ['public', 'friends', 'private'])
                ->default('public')
                ->after('gaming_enabled')
                ->comment('Visibilité du niveau gaming');

            $table->timestamp('gaming_last_activity')
                ->nullable()
                ->after('gaming_level_visibility')
                ->comment('Dernière activité gaming');

            $table->index(['gaming_enabled', 'gaming_level_visibility']);
            $table->index('gaming_last_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gaming_preferences',
                'gaming_enabled',
                'gaming_level_visibility',
                'gaming_last_activity'
            ]);
        });
    }
};
