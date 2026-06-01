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
        Schema::create('quests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Propriétaire de la quête');

            $table->string('name', 100)
                ->comment('Nom de la quête (ex: Voyage au Japon, MacBook Pro)');

            $table->decimal('target_amount', 10, 2)
                ->unsigned()
                ->comment('Montant cible à atteindre en euros');

            $table->decimal('current_amount', 10, 2)
                ->unsigned()
                ->default(0)
                ->comment('Montant actuellement économisé');

            $table->date('target_date')
                ->nullable()
                ->comment('Date cible optionnelle');

            $table->string('emoji', 10)
                ->default('🎯')
                ->comment('Emoji représentant la quête (choisi à la création)');

            $table->enum('status', ['active', 'completed', 'abandoned'])
                ->default('active')
                ->comment('Statut de la quête');

            $table->boolean('is_main')
                ->default(true)
                ->comment('Quête principale affichée sur le dashboard');

            $table->timestamp('completed_at')
                ->nullable()
                ->comment('Date d\'accomplissement de la quête');

            $table->timestamps();
            $table->softDeletes();

            // Index essentiels
            $table->index(['user_id', 'status'],   'idx_quests_user_status');
            $table->index(['user_id', 'is_main'],  'idx_quests_user_main');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
