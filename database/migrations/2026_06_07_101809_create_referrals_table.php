<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();

            // Parrain
            $table->foreignId('referrer_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Filleul (null tant que non inscrit)
            $table->foreignId('referred_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Code unique du parrain
            $table->string('referral_code', 12)->unique();

            // Email invité (optionnel, pour tracking)
            $table->string('invited_email')->nullable();

            // Statut
            $table->enum('status', ['pending', 'completed', 'rewarded'])
                ->default('pending');

            // Récompenses
            $table->integer('referrer_xp')->default(200);   // XP parrain
            $table->integer('referred_freezes')->default(1); // Freezes filleul

            // Dates
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            // Index
            $table->index('referral_code');
            $table->index('referrer_id');
            $table->index('status');
        });

        // Ajouter le code parrain sur la table users
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->unique()->nullable()->after('preferences');
            $table->unsignedInteger('referral_count')->default(0)->after('referral_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['referral_code', 'referral_count']);
        });
    }
};
