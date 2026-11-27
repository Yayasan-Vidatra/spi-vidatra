<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Penanda tetap untuk subfolder baku yang dipakai modul lain: "02 Permintaan Dokumen" menampung slot
     * unggah dan "04 Bukti Unit" menerima salinan slot yang Diterima (US-15, US-17). Nama dan urutan templat
     * bisa diubah di Pengaturan (US-29), penanda ini tidak.
     */
    public function up(): void
    {
        Schema::table('folder_templates', function (Blueprint $table) {
            $table->string('purpose', 30)->nullable()->unique()->after('sort_order');
        });

        // Templat yang sudah ada diisi dari urutannya, sama seperti FolderTemplateSeeder.
        DB::table('folder_templates')->where('sort_order', 2)->update(['purpose' => 'permintaan-dokumen']);
        DB::table('folder_templates')->where('sort_order', 4)->update(['purpose' => 'bukti-unit']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folder_templates', function (Blueprint $table) {
            $table->dropUnique(['purpose']);
            $table->dropColumn('purpose');
        });
    }
};
