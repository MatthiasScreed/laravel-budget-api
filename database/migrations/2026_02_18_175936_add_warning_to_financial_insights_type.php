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
        Schema::table('financial_insights', function (Blueprint $table) {
            $table->enum('type', [
                'cost_reduction',
                'savings_opportunity',
                'behavioral_pattern',
                'goal_acceleration',
                'budget_alert',
                'unusual_spending',
                'warning',
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_insights', function (Blueprint $table) {
            $table->enum('type', [
                'cost_reduction',
                'savings_opportunity',
                'behavioral_pattern',
                'goal_acceleration',
                'budget_alert',
                'unusual_spending',
            ])->change();
        });
    }
};
