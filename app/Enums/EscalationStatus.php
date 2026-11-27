<?php

namespace App\Enums;

/**
 * Status eskalasi (UC-17): Diteruskan ke Dewan Pengawas sampai objeknya selesai, misalnya slot Diterima.
 */
enum EscalationStatus: string
{
    case Diteruskan = 'diteruskan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Diteruskan => 'Diteruskan',
            self::Selesai => 'Selesai',
        };
    }
}
