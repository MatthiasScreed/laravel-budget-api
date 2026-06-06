<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nombre de freezes disponibles (max 3)
            $table->unsignedTinyInteger('streak_freezes')->default(0)->after('engagement_score');
            // Total de freezes utilisés (pour stats)
            $table->unsignedInteger('streak_freezes_used')->default(0)->after('streak_freezes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['streak_freezes', 'streak_freezes_used']);
        });
    }
};
