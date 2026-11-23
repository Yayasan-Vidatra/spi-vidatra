<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Login hanya lewat Google (US-01), jadi kolom dan tabel kata sandi lokal dihapus.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->foreignId('role_id')->nullable()->after('google_id')->constrained();
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_used_timestep')->nullable();
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropUnique(['google_id']);
            $table->dropColumn([
                'google_id',
                'is_active',
                'last_login_at',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_last_used_timestep',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable();
            $table->rememberToken();
        });
    }
};
