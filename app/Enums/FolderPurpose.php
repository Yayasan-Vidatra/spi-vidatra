<?php

namespace App\Enums;

/**
 * Subfolder baku penugasan yang dipakai modul permintaan dokumen (US-15, US-17), ditandai di folder_templates.
 */
enum FolderPurpose: string
{
    case PermintaanDokumen = 'permintaan-dokumen';
    case BuktiUnit = 'bukti-unit';
}
