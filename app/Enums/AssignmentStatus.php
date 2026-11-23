<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Aktif = 'aktif';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Ditutup => 'Ditutup',
        };
    }
}
