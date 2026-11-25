<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Permintaan dokumen ke satu unit (US-15). Slot unggahnya disimpan di subfolder permintaan di bawah
     * "02 Permintaan Dokumen". Tenggat berupa tanggal, berlaku sampai akhir hari WITA (US-18). Waktu pengingat
     * dicatat agar H-3 dan hari-H masing-masing terkirim sekali, dan dikosongkan lagi saat tenggat diubah.
     */
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained();
            $table->unsignedSmallInteger('sequence_number');
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('folder_id')->unique()->constrained();
            $table->string('status', 20);
            $table->date('due_date');
            $table->timestamp('due_soon_reminded_at')->nullable();
            $table->timestamp('due_today_reminded_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Penjaga terakhir agar dua pembuatan bersamaan tidak mendapat nomor urut yang sama.
            $table->unique(['assignment_id', 'sequence_number'], 'document_requests_assignment_sequence_unique');
            // Pengingat tenggat harian mencari permintaan terbuka berdasarkan tanggal tenggat.
            $table->index(['status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
