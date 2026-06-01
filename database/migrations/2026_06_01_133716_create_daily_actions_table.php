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
        Schema::create('daily_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Utilisateur ayant réalisé l\'action');

            $table->foreignId('quest_id')
                ->nullable()
                ->constrained('quests')
                ->onDelete('set null')
                ->comment('Quête associée (null si action générale)');

            $table->enum('type', ['save', 'spend'])
                ->comment('save = économie réalisée, spend = dépense enregistrée');

            $table->decimal('amount', 10, 2)
                ->unsigned()
                ->comment('Montant en euros (toujours positif, le type détermine le sens)');

            $table->string('reason', 100)
                ->nullable()
                ->comment('Raison rapide (ex: J\'ai cuisiné, Kebab, Netflix)');

            $table->enum('reason_preset', [
                'cooked',       // J\'ai cuisiné
                'avoided',      // J\'ai évité un achat
                'transport',    // J\'ai pris les transports
                'other_save',   // Autre économie
                'food',         // Nourriture / restaurant
                'shopping',     // Shopping
                'subscription', // Abonnement
                'other_spend',  // Autre dépense
            ])->nullable()->comment('Raison prédéfinie pour affichage rapide');

            $table->unsignedSmallInteger('xp_earned')
                ->default(0)
                ->comment('XP gagnée grâce à cette action');

            $table->date('action_date')
                ->comment('Date de l\'action (peut différer de created_at)');

            $table->timestamps();

            // Index essentiels pour les requêtes fréquentes
            $table->index(['user_id', 'action_date'],       'idx_daily_actions_user_date');
            $table->index(['user_id', 'type', 'action_date'], 'idx_daily_actions_user_type_date');
            $table->index(['quest_id', 'action_date'],      'idx_daily_actions_quest_date');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_actions');
    }
};
