<?php

namespace App\Enums;

/**
 * Jenis aksi yang dicatat di log audit (F-09, US-24).
 */
enum AuditAction: string
{
    case DemoRoleAssigned = 'akun.peran_demo_ditetapkan';
    case DemoUnitAssigned = 'akun.unit_demo_ditetapkan';
    case AssignmentCreated = 'penugasan.dibuat';
    case AssignmentClosed = 'penugasan.ditutup';
    case AssignmentReopened = 'penugasan.dibuka_kembali';
    case AuditorAssigned = 'penugasan.auditor_ditunjuk';
    case AuditorRevoked = 'penugasan.auditor_dicabut';
    case ConfidentialAccessChanged = 'penugasan.akses_rahasia_diubah';
    case FolderCreated = 'folder.dibuat';
    case FolderRenamed = 'folder.diubah_nama';
    case FolderMoved = 'folder.dipindah';
    case FolderArchived = 'folder.diarsipkan';
    case FolderLabelChanged = 'folder.label_diubah';
    case FileUploaded = 'berkas.diunggah';
    case FileVersionAdded = 'berkas.versi_baru';
    case FileVersionRestored = 'berkas.versi_dipulihkan';
    case FileLabelChanged = 'berkas.label_diubah';
    case FileViewed = 'berkas.dibuka';
    case FileDownloaded = 'berkas.diunduh';
    case FileScanned = 'berkas.dipindai';
    case FileQuarantined = 'berkas.dikarantina';
    case FileEncrypted = 'berkas.dienkripsi';
    case FileDecrypted = 'berkas.didekripsi';
    case FileEncryptionRescheduled = 'berkas.enkripsi_dijadwalkan_ulang';
    case FileDeleted = 'berkas.dihapus';
    case FileRestored = 'berkas.dipulihkan';
    case FilePurged = 'berkas.dihapus_permanen';
    case DocumentRequestCreated = 'permintaan.dibuat';

    /**
     * Label aksi berkas untuk riwayat akses di H-06.
     */
    public function label(): string
    {
        return match ($this) {
            self::FileUploaded => 'Mengunggah',
            self::FileVersionAdded => 'Mengunggah versi baru',
            self::FileVersionRestored => 'Memulihkan versi',
            self::FileLabelChanged => 'Mengubah label',
            self::FileViewed => 'Membuka pratinjau',
            self::FileDownloaded => 'Mengunduh',
            self::FileScanned => 'Pemindaian antivirus: bersih',
            self::FileQuarantined => 'Pemindaian antivirus: terinfeksi, dikarantina',
            self::FileEncrypted => 'Isi dienkripsi',
            self::FileDecrypted => 'Isi didekripsi',
            self::FileEncryptionRescheduled => 'Enkripsi ulang tertahan, dijadwalkan ulang',
            self::FileDeleted => 'Memindah ke Tempat Sampah',
            self::FileRestored => 'Memulihkan dari Tempat Sampah',
            self::FilePurged => 'Menghapus permanen',
            default => $this->value,
        };
    }
}
