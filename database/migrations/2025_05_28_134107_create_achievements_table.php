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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Nom du succès');
            $table->string('slug', 100)->unique()->comment('Identifiant unique');
            $table->text('description')->comment('Description du succès');
            $table->string('icon', 100)->nullable()->comment('Icône du succès');
            $table->string('color', 7)->default('#3B82F6')->comment('Couleur du succès');
            $table->enum('type', ['transaction', 'goal', 'streak', 'milestone', 'social'])->comment('Type de succès');
            $table->json('criteria')->comment('Critères pour débloquer');
            $table->unsignedInteger('points')->default(0)->comment('Points XP accordés');
            $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common')->comment('Rareté');
            $table->boolean('is_active')->default(true)->comment('Succès actif');
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['rarity', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
