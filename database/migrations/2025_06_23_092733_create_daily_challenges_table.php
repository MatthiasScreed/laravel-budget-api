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
        Schema::create('daily_challenges', function (Blueprint $table) {
            $table->id();

            $table->date('challenge_date')
                ->comment('Date du défi');

            $table->string('type', 50)
                ->comment('Type de défi (transaction, goal, streak, etc.)');

            $table->string('title', 150)
                ->comment('Titre du défi');

            $table->text('description')
                ->comment('Description du défi');

            $table->json('criteria')
                ->comment('Critères du défi');

            $table->unsignedInteger('reward_xp')
                ->default(0)
                ->comment('XP de récompense');

            $table->json('bonus_rewards')
                ->nullable()
                ->comment('Récompenses bonus');

            $table->enum('difficulty', ['easy', 'medium', 'hard'])
                ->default('medium')
                ->comment('Difficulté du défi');

            $table->boolean('is_global')
                ->default(true)
                ->comment('Défi global ou personnalisé');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Défi actif');

            $table->timestamps();

            $table->unique(['challenge_date', 'type'], 'idx_daily_challenge_date_type');
            $table->index(['challenge_date', 'is_active']);
            $table->index(['type', 'difficulty']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_challenges');
    }
};
