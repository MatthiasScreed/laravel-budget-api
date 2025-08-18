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
        Schema::create('bank_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Informations bancaires
            $table->string('bank_name');
            $table->string('bank_code')->nullable();
            $table->text('account_number_encrypted')->nullable();
            $table->enum('account_type', ['checking', 'savings', 'credit', 'investment'])->default('checking');

            // Connexion API
            $table->string('connection_id')->unique(); // ID Bridge/autre provider
            $table->text('access_token_encrypted');
            $table->text('refresh_token_encrypted')->nullable();
            $table->enum('provider', ['bridge', 'budget_insight', 'nordigen'])->default('bridge');

            // Statut et synchronisation
            $table->enum('status', ['active', 'expired', 'error', 'disabled'])->default('active');
            $table->timestamp('last_sync_at')->nullable();
            $table->integer('error_count')->default(0);
            $table->text('last_error')->nullable();

            // Configuration
            $table->boolean('auto_sync_enabled')->default(true);
            $table->integer('sync_frequency_hours')->default(6);

            $table->timestamps();
            $table->softDeletes();

            // Index pour performance
            $table->index(['user_id', 'status']);
            $table->index(['provider', 'connection_id']);
            $table->index('last_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_connections');
    }
};
