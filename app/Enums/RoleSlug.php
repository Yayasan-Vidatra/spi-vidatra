<?php

namespace App\Enums;

/**
 * Slug peran yang tersimpan di tabel `roles`.
 *
 * Enum ini hanya dipakai untuk merujuk peran dari kode; atribut peran
 * (nama, kewajiban 2FA) tetap berasal dari basis data.
 */
enum RoleSlug: string
{
    case DewanPengawas = 'dewan-pengawas';
    case AuditorSpi = 'auditor-spi';
    case PicUnit = 'pic-unit';
    case PengurusYayasan = 'pengurus-yayasan';
    case PeninjauTamu = 'peninjau-tamu';
    case SuperAdmin = 'super-admin';
}
