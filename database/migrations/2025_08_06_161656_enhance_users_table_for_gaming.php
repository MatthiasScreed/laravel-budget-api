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
            // Colonnes d'engagement si elles n'existent pas encore
            if (! Schema::hasColumn('users', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'total_sessions')) {
                $table->integer('total_sessions')->default(0);
            }
            if (! Schema::hasColumn('users', 'engagement_score')) {
                $table->decimal('engagement_score', 5, 2)->default(0.00); // Score d'engagement 0-100
            }
            if (! Schema::hasColumn('users', 'preferred_notifications')) {
                $table->json('preferred_notifications')->nullable(); // Préférences notifications
            }
            if (! Schema::hasColumn('users', 'gaming_preferences')) {
                $table->json('gaming_preferences')->nullable(); // Préférences gaming
            }
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 50)->default('Europe/Paris');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_activity_at', 'total_sessions', 'engagement_score',
                'preferred_notifications', 'gaming_preferences', 'timezone',
            ]);
        });
    }
};
