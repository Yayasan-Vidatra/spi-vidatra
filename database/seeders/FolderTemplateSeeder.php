<?php

namespace Database\Seeders;

use App\Enums\Classification;
use App\Models\FolderTemplate;
use Illuminate\Database\Seeder;

/**
 * Tujuh subfolder baku penugasan (Rancangan 9.1). Templat final dari SPI menyusul,
 * dan nanti bisa diubah dari Pengaturan (US-29).
 */
class FolderTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            ['01 Perencanaan', 'Surat tugas, program kerja audit'],
            ['02 Permintaan Dokumen', 'Slot unggah dari unit'],
            ['03 Kertas Kerja', 'KKA, catatan auditor'],
            ['04 Bukti Unit', 'Dokumen yang sudah diterima'],
            ['05 Temuan dan Tanggapan', null],
            ['06 Laporan', 'Draf dan laporan final'],
            ['07 Tindak Lanjut', 'Pemantauan rekomendasi'],
        ];

        foreach ($templates as $index => [$name, $description]) {
            FolderTemplate::query()->updateOrCreate(
                ['sort_order' => $index + 1],
                [
                    'name' => $name,
                    'description' => $description,
                    'default_classification' => Classification::Internal,
                ],
            );
        }
    }
}
