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
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->comment('Nom du défi');
            $table->text('description')->comment('Description du défi');
            $table->string('icon', 100)->nullable()->comment('Icône du défi');
            $table->enum('type', ['personal', 'community', 'seasonal'])->comment('Type de défi');
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'expert'])->default('medium')->comment('Difficulté');
            $table->json('criteria')->comment('Critères du défi');
            $table->unsignedInteger('reward_xp')->default(0)->comment('Récompense XP');
            $table->json('reward_items')->nullable()->comment('Récompenses additionnelles');
            $table->date('start_date')->comment('Date de début');
            $table->date('end_date')->comment('Date de fin');
            $table->boolean('is_active')->default(true)->comment('Défi actif');
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
