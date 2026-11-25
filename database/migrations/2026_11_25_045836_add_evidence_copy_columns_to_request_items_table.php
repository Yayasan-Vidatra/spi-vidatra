<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versi berkas slot yang diterima auditor (US-17); hanya versi ini yang disalin ke 04 Bukti Unit.
     * `evidence_copy_pending_since` diisi saat penyalinan dijadwalkan dan dikosongkan setelah salinan
     * terbentuk, sehingga permintaan:periksa-salinan bisa menjadwalkan ulang salinan yang tertahan.
     */
    public function up(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->foreignId('accepted_version_id')->nullable()->after('reviewed_at')->constrained('file_versions');
            $table->timestamp('evidence_copy_pending_since')->nullable()->after('evidence_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_version_id');
            $table->dropColumn('evidence_copy_pending_since');
        });
    }
};
