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
        Schema::create('user_activity_log', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('action', 100)
                ->comment('Action effectuée');

            $table->string('category', 50)
                ->default('general')
                ->comment('Catégorie d\'action (gaming, financial, system)');

            $table->text('description')
                ->nullable()
                ->comment('Description de l\'action');

            $table->json('metadata')
                ->nullable()
                ->comment('Données additionnelles');

            $table->string('ip_address', 45)
                ->nullable()
                ->comment('Adresse IP');

            $table->text('user_agent')
                ->nullable()
                ->comment('User agent');

            $table->timestamp('performed_at')
                ->default(now())
                ->comment('Date d\'exécution');

            $table->timestamps();

            $table->index(['user_id', 'performed_at']);
            $table->index(['action', 'category']);
            $table->index(['category', 'performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_log');
    }
};
