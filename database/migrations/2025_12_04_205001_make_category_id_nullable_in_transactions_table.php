<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rend category_id nullable pour permettre les transactions
     * en attente de catégorisation (status = 'pending')
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Supprimer la contrainte de clé étrangère existante
            $table->dropForeign(['category_id']);

            // Modifier la colonne pour la rendre nullable
            $table->foreignId('category_id')
                ->nullable()
                ->change();

            // Recréer la contrainte de clé étrangère
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Supprimer la contrainte
            $table->dropForeign(['category_id']);

            // Remettre NOT NULL (attention : nécessite que toutes les transactions aient une catégorie)
            $table->foreignId('category_id')
                ->nullable(false)
                ->change();

            // Recréer la contrainte
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('restrict');
        });
    }
};
