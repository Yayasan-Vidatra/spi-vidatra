<?php

namespace App\Enums;

/**
 * Jenis eskalasi tenggat Piagam ke Dewan Pengawas (F-21, UC-17). Tindak lanjut (tanggapan rancangan LHA,
 * rencana aksi, temuan berisiko tinggi) menyusul sebagai jenis baru di sini.
 */
enum EscalationType: string
{
    /**
     * Slot permintaan dokumen yang belum Diterima 10 hari kerja setelah tenggat (Piagam Pasal 17 ayat (2)).
     */
    case PermintaanDokumen = 'permintaan_dokumen';

    public function label(): string
    {
        return match ($this) {
            self::PermintaanDokumen => 'Permintaan dokumen',
        };
    }
}
