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
        Schema::create('financial_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Type d'insight
            $table->enum('type', [
                'cost_reduction',
                'savings_opportunity',
                'behavioral_pattern',
                'goal_acceleration',
                'budget_alert',
                'unusual_spending'
            ]);

            // Priorité (1 = critique, 5 = info)
            $table->tinyInteger('priority')->default(3);

            // Contenu
            $table->string('title');
            $table->text('description');
            $table->string('icon')->default('💡');

            // Action suggérée
            $table->string('action_label')->nullable();
            $table->json('action_data')->nullable(); // Données pour exécuter l'action

            // Impact financier
            $table->decimal('potential_saving', 10, 2)->nullable();
            $table->json('goal_impact')->nullable(); // Impact sur chaque objectif

            // Métadonnées
            $table->json('metadata')->nullable(); // Données contextuelles
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('acted_at')->nullable();

            $table->timestamps();

            // Index
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'is_read']);
            $table->index(['type', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_insights');
    }
};
