<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ukuran, MIME, hash, nama simpan, dan status pindai ada di file_versions karena berbeda per versi (US-10);
     * files menunjuk versi terkini lewat current_version_id.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained();
            $table->string('original_name');
            $table->string('classification', 20);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Nama sama di folder yang sama menjadi versi berikutnya, bukan berkas baru (US-10).
            $table->unique(['folder_id', 'original_name'], 'files_folder_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
