<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ✅ DB::statement DOIT être en DEHORS de Schema::table()
        DB::statement("
            ALTER TABLE bank_connections
            MODIFY COLUMN status
            ENUM('pending', 'active', 'expired', 'error', 'disabled')
            DEFAULT 'pending'
        ");
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
