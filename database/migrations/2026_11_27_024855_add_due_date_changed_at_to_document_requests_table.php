<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Waktu tenggat terakhir diubah (US-18). Kosong selama tenggat belum pernah diubah; pengingat H-3 tidak
     * dikirim untuk tenggat yang dibuat atau diubah pada atau setelah tanggal H-3-nya.
     */
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->timestamp('due_date_changed_at')->nullable()->after('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn('due_date_changed_at');
        });
    }
};
