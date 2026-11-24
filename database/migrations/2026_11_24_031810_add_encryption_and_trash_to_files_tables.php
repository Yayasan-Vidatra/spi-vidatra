<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * US-12: penanda versi yang isinya terenkripsi. US-13: Tempat Sampah lewat soft delete. Nama unik per
     * folder kini hanya berlaku untuk berkas aktif, sehingga nama berkas di Tempat Sampah bisa dipakai lagi.
     */
    public function up(): void
    {
        Schema::table('file_versions', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('sha256');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users');
            // Kosong untuk berkas di Tempat Sampah; MySQL membolehkan banyak NULL di unique index.
            $table->string('active_name')->nullable()->storedAs('if(`deleted_at` is null, `original_name`, null)')->after('original_name');
        });

        // Index baru dibuat lebih dulu agar foreign key folder_id selalu punya index pendukung.
        Schema::table('files', function (Blueprint $table) {
            $table->unique(['folder_id', 'active_name'], 'files_folder_active_name_unique');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->dropUnique('files_folder_name_unique');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->unique(['folder_id', 'original_name'], 'files_folder_name_unique');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->dropUnique('files_folder_active_name_unique');
            $table->dropIndex(['deleted_at']);
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['active_name', 'deleted_at']);
        });

        Schema::table('file_versions', function (Blueprint $table) {
            $table->dropColumn('is_encrypted');
        });
    }
};
