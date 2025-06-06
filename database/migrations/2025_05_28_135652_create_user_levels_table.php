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
        Schema::create('user_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('level')->default(1)->comment('Niveau actuel');
            $table->unsignedBigInteger('total_xp')->default(0)->comment('XP total');
            $table->unsignedBigInteger('current_level_xp')->default(0)->comment('XP niveau actuel');
            $table->unsignedBigInteger('next_level_xp')->default(100)->comment('XP requis niveau suivant');
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['level']);
            $table->index(['total_xp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_levels');
    }
};
