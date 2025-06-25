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
        Schema::create('gaming_rewards', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100)
                ->comment('Nom de la récompense');

            $table->string('type', 50)
                ->comment('Type (badge, title, bonus_xp, item, etc.)');

            $table->text('description')
                ->nullable()
                ->comment('Description de la récompense');

            $table->string('icon', 100)
                ->nullable()
                ->comment('Icône de la récompense');

            $table->string('color', 7)
                ->default('#3B82F6')
                ->comment('Couleur de la récompense');

            $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])
                ->default('common')
                ->comment('Rareté de la récompense');

            $table->json('criteria')
                ->comment('Critères pour obtenir la récompense');

            $table->json('reward_data')
                ->nullable()
                ->comment('Données spécifiques à la récompense');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Récompense active');

            $table->boolean('is_repeatable')
                ->default(false)
                ->comment('Peut être obtenue plusieurs fois');

            $table->date('available_from')
                ->nullable()
                ->comment('Disponible à partir de');

            $table->date('available_until')
                ->nullable()
                ->comment('Disponible jusqu\'à');

            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['rarity', 'is_active']);
            $table->index(['available_from', 'available_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gaming_rewards');
    }
};
