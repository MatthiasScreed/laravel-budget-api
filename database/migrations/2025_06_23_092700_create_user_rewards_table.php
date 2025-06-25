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
        Schema::create('user_rewards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('gaming_reward_id')
                ->constrained('gaming_rewards')
                ->onDelete('cascade');

            $table->timestamp('earned_at')
                ->default(now())
                ->comment('Date d\'obtention');

            $table->boolean('is_equipped')
                ->default(false)
                ->comment('Récompense équipée (pour titres, badges)');

            $table->json('metadata')
                ->nullable()
                ->comment('Données spécifiques à l\'obtention');

            $table->timestamps();

            $table->index(['user_id', 'earned_at']);
            $table->index(['user_id', 'is_equipped']);
            $table->index(['gaming_reward_id', 'earned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_rewards');
    }
};
