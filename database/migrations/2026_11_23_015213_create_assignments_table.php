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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('sequence_number');
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('team_lead_id')->nullable()->constrained('users');
            $table->string('status', 20)->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();

            // Penjaga terakhir agar dua pembuatan bersamaan tidak mendapat nomor urut yang sama (US-05).
            $table->unique(['year', 'sequence_number'], 'assignments_year_sequence_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
