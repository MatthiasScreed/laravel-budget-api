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
        Schema::create('categories', function (Blueprint $table) {
            // ✅ AJOUT DE LA CLÉ PRIMAIRE MANQUANTE
            $table->id();

            $table->string('name', 100)
                ->comment('Nom de la catégorie');

            $table->text('description')
                ->nullable()
                ->comment('Description détaillée de la catégorie');

            $table->enum('type', ['income', 'expense'])
                ->default('expense')
                ->comment('Type : revenus ou dépenses');

            $table->string('color', 7)
                ->default('#6B7280')
                ->comment('Couleur hexadécimale pour l\'affichage');

            $table->string('icon', 50)
                ->nullable()
                ->comment('Icône associée à la catégorie');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Catégorie active ou archivée');

            $table->boolean('is_system')
                ->default(false)
                ->comment('Catégorie système (non modifiable)');

            $table->unsignedSmallInteger('sort_order')
                ->default(0)
                ->comment('Ordre d\'affichage');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Propriétaire (null = catégorie globale)');

            $table->timestamps();
            $table->softDeletes();

            // Index pour optimiser les requêtes
            $table->index(['user_id', 'type', 'is_active'], 'idx_categories_user_type_active');
            $table->index(['type', 'is_system'], 'idx_categories_type_system');
            $table->index(['is_active', 'sort_order'], 'idx_categories_active_sort');

            // ✅ CORRECTION DE LA CONTRAINTE UNIQUE POUR GÉRER user_id NULLABLE
            $table->index(['user_id', 'name', 'type'], 'idx_unique_user_category_name_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
