<?php

namespace App\Enums;

/**
 * Status slot unggah yang disimpan (KF-08). Terlambat tidak disimpan: dihitung dari tenggat untuk slot
 * yang masih Menunggu atau Perlu Perbaikan (US-18).
 */
enum RequestItemStatus: string
{
    case Menunggu = 'menunggu';
    case Diunggah = 'diunggah';
    case Diterima = 'diterima';
    case PerluPerbaikan = 'perlu_perbaikan';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Diunggah => 'Diunggah',
            self::Diterima => 'Diterima',
            self::PerluPerbaikan => 'Perlu Perbaikan',
        };
    }

    /**
     * Whether the unit still owes an upload for the slot, so the slot counts as late once the deadline passes.
     */
    public function awaitsUpload(): bool
    {
        return $this === self::Menunggu || $this === self::PerluPerbaikan;
    }
}
