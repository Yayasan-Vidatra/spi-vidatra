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
        Schema::create('confidentiality_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('confidentiality_statement_id')
                ->constrained(indexName: 'conf_accept_statement_foreign');
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Nama otomatis melebihi batas 64 karakter MySQL untuk identifier.
            $table->unique(['user_id', 'confidentiality_statement_id'], 'conf_accept_user_statement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confidentiality_acceptances');
    }
};
