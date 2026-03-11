<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') !== 'sqlite' && config('database.connections.testing.driver') !== 'sqlite') {
            DB::statement("
                ALTER TABLE bank_connections
                MODIFY COLUMN status
                ENUM('pending', 'active', 'expired', 'error', 'disabled')
                DEFAULT 'pending'
            ");
        } else {
            Schema::table('bank_connections', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE bank_connections
            MODIFY COLUMN status
            ENUM('active', 'expired', 'error', 'disabled')
            DEFAULT 'active'
        ");
    }
};
