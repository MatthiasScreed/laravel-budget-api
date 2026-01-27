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
        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // ✅ CORRECTION : Enum avec toutes les valeurs possibles
            $table->enum('type', [
                'daily_login',
                'daily_transaction',
                'weekly_budget',
                'monthly_saving',
            ])->index();

            $table->integer('current_count')->default(0);
            $table->integer('best_count')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->boolean('is_active')->default(true);

            // ✅ AJOUT : Colonne bonus_claimed_at manquante
            $table->timestamp('bonus_claimed_at')->nullable();

            $table->timestamps();

            // Index composé pour performance
            $table->unique(['user_id', 'type']);
            $table->index(['is_active', 'type']);
            $table->index(['best_count', 'type']); // Pour les leaderboards
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streaks');
    }
};
