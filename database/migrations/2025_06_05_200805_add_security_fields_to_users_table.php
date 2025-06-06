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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('password');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('last_activity_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->boolean('two_factor_enabled')->default(false)->after('locked_until');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->json('security_questions')->nullable()->after('two_factor_recovery_codes');

            $table->index('locked_until');
            $table->index('failed_login_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_changed_at',
                'failed_login_attempts',
                'locked_until',
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'security_questions'
            ]);
        });
    }
};
