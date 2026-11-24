<?php

namespace App\Enums;

/**
 * Status pemindaian antivirus per versi berkas (US-09). Hanya versi Bersih yang bisa dibuka atau diunduh.
 */
enum ScanStatus: string
{
    case Menunggu = 'menunggu';
    case Bersih = 'bersih';
    case Terinfeksi = 'terinfeksi';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu pindai',
            self::Bersih => 'Bersih',
            self::Terinfeksi => 'Terinfeksi (dikarantina)',
        };
    }
}
