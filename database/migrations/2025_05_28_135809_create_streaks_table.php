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
            $table->enum('type', ['daily_transaction', 'weekly_goal', 'monthly_saving'])->comment('Type de série');
            $table->unsignedInteger('current_count')->default(0)->comment('Série actuelle');
            $table->unsignedInteger('best_count')->default(0)->comment('Meilleure série');
            $table->date('last_activity_date')->nullable()->comment('Dernière activité');
            $table->boolean('is_active')->default(true)->comment('Série active');
            $table->timestamps();

            $table->unique(['user_id', 'type']);
            $table->index(['user_id', 'is_active']);
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
