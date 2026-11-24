<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sejak kapan enkripsi ulang berkas dijadwalkan dan belum selesai (US-12), agar berkas yang tertahan
     * terlalu lama terdeteksi dan dijadwalkan ulang oleh berkas:periksa-enkripsi.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->timestamp('encryption_pending_since')->nullable()->after('classification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('encryption_pending_since');
        });
    }
};
