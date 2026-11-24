<?php

namespace App\Enums;

/**
 * Versi yang isinya belum mengikuti label berkas (US-12): label sudah berubah, enkripsi ulang di antrean
 * belum selesai.
 */
enum EncryptionSyncStatus: string
{
    case MenungguEnkripsi = 'menunggu_enkripsi';
    case MenungguDekripsi = 'menunggu_dekripsi';

    public function label(): string
    {
        return match ($this) {
            self::MenungguEnkripsi => 'Menunggu enkripsi',
            self::MenungguDekripsi => 'Menunggu dekripsi',
        };
    }
}
