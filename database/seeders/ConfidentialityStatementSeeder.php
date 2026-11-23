<?php

namespace Database\Seeders;

use App\Models\ConfidentialityStatement;
use Illuminate\Database\Seeder;

/**
 * Teks awal pernyataan kerahasiaan (US-03). Ini draf; teks final dari SPI
 * diganti lewat H-13 setelah US-29 selesai.
 */
class ConfidentialityStatementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (ConfidentialityStatement::query()->exists()) {
            return;
        }

        ConfidentialityStatement::query()->create([
            'body' => <<<'TEXT'
                Dengan menggunakan Folder Kerja SPI Yayasan Vidatra, saya menyatakan bahwa:

                1. Saya akan menjaga kerahasiaan seluruh dokumen, data, dan informasi yang saya akses melalui sistem ini, dan hanya menggunakannya untuk keperluan tugas saya.
                2. Saya tidak akan membagikan, menyalin, atau meneruskan dokumen kepada pihak yang tidak berhak, baik di dalam maupun di luar Yayasan Vidatra.
                3. Saya tidak akan meminjamkan akun saya atau memberikan kode autentikasi kepada siapa pun.
                4. Saya memahami bahwa setiap akses dan aksi saya di sistem ini tercatat di log audit.
                5. Saya bersedia menerima konsekuensi sesuai peraturan Yayasan Vidatra apabila melanggar pernyataan ini.
                TEXT,
        ]);
    }
}
