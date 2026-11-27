<?php

namespace App\Enums;

/**
 * Jenis hari libur dalam hitungan hari kerja (F-21): libur nasional atau cuti bersama menurut SKB tiga menteri.
 */
enum HolidayType: string
{
    case LiburNasional = 'libur_nasional';
    case CutiBersama = 'cuti_bersama';

    public function label(): string
    {
        return match ($this) {
            self::LiburNasional => 'Libur nasional',
            self::CutiBersama => 'Cuti bersama',
        };
    }
}
