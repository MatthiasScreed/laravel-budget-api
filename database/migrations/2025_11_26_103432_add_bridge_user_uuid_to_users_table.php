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
            $table->string('bridge_user_uuid')->nullable()->after('id');
            $table->index('bridge_user_uuid'); // ✅ Point-virgule ajouté
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ✅ Supprimer l'index d'abord, puis la colonne
            $table->dropIndex(['bridge_user_uuid']);
            $table->dropColumn('bridge_user_uuid');
        });
    }
};
