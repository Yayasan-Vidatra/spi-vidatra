<?php

namespace App\Policies;

use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Hak akses berkas (US-09 sampai US-14) mengikuti FolderPolicy: berkas terlihat oleh yang boleh melihat
 * foldernya, dan berkas berlabel Rahasia hanya oleh yang punya akses Rahasia. Dicek di server di setiap
 * permintaan, termasuk lewat URL langsung dan signed URL.
 */
class FilePolicy
{
    /**
     * Pencarian berkas (US-14) dan Tempat Sampah (US-13): Dewan Pengawas dan Auditor SPI.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas) || $user->hasRole(RoleSlug::AuditorSpi);
    }

    /**
     * Berkas di Tempat Sampah tidak bisa dibuka atau diunduh, hanya dipulihkan.
     */
    public function view(User $user, File $file): bool
    {
        return ! $file->trashed() && $this->canSee($user, $file);
    }

    /**
     * Isi berkas baru bisa diunduh setelah versinya lolos pemindaian antivirus. Tetap boleh saat
     * penugasan ditutup. PIC Unit juga boleh mengunduh berkas slot unitnya (US-16), termasuk yang berlabel
     * Rahasia karena itu dokumen unitnya sendiri (log deviasi), tanpa membuka H-06.
     */
    public function download(User $user, File $file, FileVersion $version): Response
    {
        if ($version->file_id !== $file->id || ! ($this->view($user, $file) || $this->isSlotFileOfUsersUnit($user, $file))) {
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
     * Auditor hanya menghapus berkas yang semua versinya ia unggah, agar pekerjaan orang lain tidak ikut
     * terhapus; Dewan Pengawas menghapus berkas apa pun. Ditolak di folder terkunci atau terarsip (US-13).
     */
    public function delete(User $user, File $file): bool
    {
        if (! $this->uploadVersion($user, $file)) {
            return false;
        }

        if ($user->hasRole(RoleSlug::DewanPengawas)) {
            return true;
        }

        return $user->hasRole(RoleSlug::AuditorSpi)
            && ! $file->versions()->where('uploaded_by', '!=', $user->id)->exists();
    }

    /**
     * Hanya Dewan Pengawas yang memulihkan berkas dari Tempat Sampah, ke folder yang belum terkunci.
     */
    public function restore(User $user, File $file): bool
    {
        return $file->trashed()
            && $user->hasRole(RoleSlug::DewanPengawas)
            && $this->canSee($user, $file)
            && Gate::forUser($user)->allows('uploadFile', $file->folder);
    }

    /**
     * Hanya Dewan Pengawas yang menghapus permanen sebelum 30 hari, dengan aturan folder yang sama seperti pulihkan.
     */
    public function forceDelete(User $user, File $file): bool
    {
        return $this->restore($user, $file);
    }

    /**
     * Riwayat akses dari audit_logs di H-06 hanya untuk Dewan Pengawas.
     */
    public function viewAccessHistory(User $user, File $file): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas) && $this->view($user, $file);
    }

    /**
     * Hanya berkas slot itu sendiri; berkas lain di folder 02 dan salinan di 04 mengikuti aturan biasa.
     */
    private function isSlotFileOfUsersUnit(User $user, File $file): bool
    {
        if ($file->trashed() || ! $user->hasRole(RoleSlug::PicUnit) || $user->unit_id === null) {
            return false;
        }

        return RequestItem::query()
            ->where('file_id', $file->id)
            ->whereRelation('documentRequest', 'unit_id', $user->unit_id)
            ->exists();
    }

    private function canSee(User $user, File $file): bool
    {
        if (! Gate::forUser($user)->allows('view', $file->folder)) {
            return false;
        }

        return $file->classification !== Classification::Rahasia
            || Gate::forUser($user)->allows('accessConfidential', $file->folder);
    }
}
