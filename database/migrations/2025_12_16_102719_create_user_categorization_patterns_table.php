<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour la table user_categorization_patterns
 *
 * Stocke les patterns de catégorisation appris depuis
 * les corrections manuelles des utilisateurs
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_categorization_patterns', function (Blueprint $table) {
            $table->id();

            // Relation utilisateur
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Pattern détecté (nom commerçant normalisé)
            $table->string('pattern', 100);

            // Catégorie associée
            $table->foreignId('category_id')
                ->constrained()
                ->onDelete('cascade');

            // Métadonnées d'apprentissage
            $table->integer('match_count')->default(1)
                ->comment('Nombre de fois que ce pattern a été confirmé');

            $table->decimal('confidence', 3, 2)->default(0.80)
                ->comment('Score de confiance (0.00 à 1.00)');

            $table->timestamps();

            // Index pour performance
            $table->index(['user_id', 'pattern'], 'idx_user_pattern');

            // Index composite pour recherche rapide
            $table->index(['user_id', 'confidence'], 'idx_user_confidence');

            // Contrainte d'unicité : un pattern = une catégorie par user
            $table->unique(['user_id', 'pattern', 'category_id'], 'uq_user_pattern_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_categorization_patterns');
    }
};
