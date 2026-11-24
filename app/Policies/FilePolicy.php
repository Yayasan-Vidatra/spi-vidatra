<?php

namespace App\Policies;

use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Hak akses berkas (US-09 sampai US-11) mengikuti FolderPolicy: berkas terlihat oleh yang boleh melihat
 * foldernya, dan berkas berlabel Rahasia hanya oleh yang punya akses Rahasia. Dicek di server di setiap
 * permintaan, termasuk lewat URL langsung dan signed URL.
 */
class FilePolicy
{
    public function view(User $user, File $file): bool
    {
        if (! Gate::forUser($user)->allows('view', $file->folder)) {
            return false;
        }

        return $file->classification !== Classification::Rahasia
            || Gate::forUser($user)->allows('accessConfidential', $file->folder);
    }

    /**
     * Isi berkas baru bisa diunduh setelah versinya lolos pemindaian antivirus. Tetap boleh saat
     * penugasan ditutup.
     */
    public function download(User $user, File $file, FileVersion $version): Response
    {
        if ($version->file_id !== $file->id || ! $this->view($user, $file)) {
            return Response::deny();
        }

        return $version->hasPassedScan()
            ? Response::allow()
            : Response::deny('Berkas belum bisa dibuka karena belum dinyatakan bersih oleh pemindai antivirus.');
    }

    public function preview(User $user, File $file, FileVersion $version): Response
    {
        $download = $this->download($user, $file, $version);

        if ($download->denied()) {
            return $download;
        }

        return $version->isPreviewable()
            ? Response::allow()
            : Response::deny('Pratinjau hanya tersedia untuk PDF dan gambar.');
    }

    /**
     * Folder terkunci, terarsip, atau penugasan yang ditutup menolak versi baru.
     */
    public function uploadVersion(User $user, File $file): bool
    {
        return $this->view($user, $file) && Gate::forUser($user)->allows('uploadFile', $file->folder);
    }

    /**
     * Memulihkan berarti membuat versi baru dari isi versi lama yang bersih (US-10).
     */
    public function restoreVersion(User $user, File $file, FileVersion $version): bool
    {
        return $version->file_id === $file->id
            && $version->id !== $file->current_version_id
            && $version->hasPassedScan()
            && $this->uploadVersion($user, $file);
    }

    /**
     * Aturannya sama dengan label folder: label Rahasia hanya oleh yang punya akses Rahasia,
     * menurunkan dari Rahasia hanya oleh Dewan Pengawas.
     */
    public function changeLabel(User $user, File $file, ?Classification $newClassification = null): bool
    {
        if (! $this->uploadVersion($user, $file)) {
            return false;
        }

        if ($newClassification === null) {
            return true;
        }

        if ($newClassification === Classification::Rahasia && ! Gate::forUser($user)->allows('accessConfidential', $file->folder)) {
            return false;
        }

        return ! $file->classification->isLoweredFromConfidential($newClassification)
            || $user->hasRole(RoleSlug::DewanPengawas);
    }

    /**
     * Riwayat akses dari audit_logs di H-06 hanya untuk Dewan Pengawas.
     */
    public function viewAccessHistory(User $user, File $file): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas) && $this->view($user, $file);
    }
}
