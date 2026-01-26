<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Tracking Bridge
            $table->string('bridge_transaction_id')->nullable()->after('external_transaction_id');
            $table->boolean('is_from_bridge')->default(false)->after('bridge_transaction_id');
            $table->boolean('auto_imported')->default(false)->after('is_from_bridge');
            $table->boolean('auto_categorized')->default(false)->after('auto_imported');

            // Index pour performance
            $table->index('bridge_transaction_id');
            $table->index('is_from_bridge');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['transactions_bridge_transaction_id_index']);
            $table->dropIndex(['transactions_is_from_bridge_index']);
            $table->dropIndex(['transactions_user_id_status_index']);

            $table->dropColumn([
                'bridge_transaction_id',
                'is_from_bridge',
                'auto_imported',
                'auto_categorized',
            ]);
        });
    }
};
