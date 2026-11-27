<?php

namespace Database\Seeders;

use App\Enums\Classification;
use App\Enums\FolderPurpose;
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
            ['01 Perencanaan', 'Surat tugas, program kerja audit', null],
            ['02 Permintaan Dokumen', 'Slot unggah dari unit', FolderPurpose::PermintaanDokumen],
            ['03 Kertas Kerja', 'KKA, catatan auditor', null],
            ['04 Bukti Unit', 'Dokumen yang sudah diterima', FolderPurpose::BuktiUnit],
            ['05 Temuan dan Tanggapan', null, null],
            ['06 Laporan', 'Draf dan laporan final', null],
            ['07 Tindak Lanjut', 'Pemantauan rekomendasi', null],
        ];

        foreach ($templates as $index => [$name, $description, $purpose]) {
            $template = FolderTemplate::query()->updateOrCreate(
                ['sort_order' => $index + 1],
                [
                    'name' => $name,
                    'description' => $description,
                    'default_classification' => Classification::Internal,
                ],
            );

            // Penanda dipakai modul permintaan dokumen; tidak termasuk kolom yang bisa diubah di Pengaturan.
            $template->forceFill(['purpose' => $purpose])->save();
        }
    }
}
