<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Slot unggah per dokumen yang diminta (US-15 sampai US-17). `file_id` menunjuk berkas slot di subfolder
     * permintaan; unggahan ulang menjadi versi baru berkas itu. `evidence_file_id` menunjuk salinan di
     * "04 Bukti Unit" setelah slot Diterima. Status Terlambat tidak disimpan, tetapi dihitung dari tenggat.
     */
    public function up(): void
    {
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained();
            $table->unsignedSmallInteger('sort_order');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('accepted_extensions');
            $table->string('status', 20)->index();
            $table->foreignId('file_id')->nullable()->unique()->constrained();
            $table->timestamp('submitted_at')->nullable();
            $table->text('reviewer_note')->nullable();
            // Catatan dari sistem (misalnya berkas terinfeksi), terpisah dari catatan auditor agar jelas penulisnya.
            $table->text('system_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('evidence_file_id')->nullable()->unique()->constrained('files');
            $table->timestamps();

            // Nama dokumen unik dalam satu permintaan, karena dipakai sebagai nama berkas slot.
            $table->unique(['document_request_id', 'name'], 'request_items_request_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
