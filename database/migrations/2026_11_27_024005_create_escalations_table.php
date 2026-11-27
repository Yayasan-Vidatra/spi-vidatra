<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Eskalasi tenggat Piagam ke Dewan Pengawas (F-21, KF-12, UC-17): jenis, objek, batas menurut Piagam,
     * waktu diteruskan, dan status. Satu objek hanya dieskalasi sekali per jenis.
     */
    public function up(): void
    {
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->morphs('subject');
            $table->date('charter_deadline');
            $table->timestamp('escalated_at');
            $table->string('status', 20);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'subject_type', 'subject_id']);
            // Dasbor Dewan Pengawas menampilkan eskalasi yang masih Diteruskan.
            $table->index(['status', 'escalated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
