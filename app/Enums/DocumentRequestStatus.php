<?php

namespace App\Enums;

/**
 * Status permintaan dokumen (Rancangan 9.2). Selesai setelah semua slotnya Diterima (UC-06).
 */
enum DocumentRequestStatus: string
{
    case Terbuka = 'terbuka';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::Selesai => 'Selesai',
        };
    }
}
