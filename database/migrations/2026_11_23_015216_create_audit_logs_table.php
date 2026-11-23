<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Log hanya bisa ditambah; setiap baris menyimpan hash baris sebelumnya (US-24).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('properties')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->unique();

            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
