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
        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained();
            $table->unsignedInteger('version_number');
            $table->uuid('stored_name')->unique();
            $table->string('storage_path');
            $table->unsignedBigInteger('size');
            $table->string('mime_type', 100);
            $table->char('sha256', 64);
            $table->string('scan_status', 20)->index();
            $table->timestamp('scanned_at')->nullable();
            $table->string('scan_signature')->nullable();
            $table->foreignId('restored_from_version_id')->nullable()->constrained('file_versions');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['file_id', 'version_number'], 'file_versions_file_number_unique');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('file_versions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('file_versions');
    }
};
