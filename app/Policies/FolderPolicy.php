<?php

namespace App\Policies;

use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Hak akses folder (US-07): RBAC ditambah label klasifikasi, dicek di server di setiap permintaan.
 */
class FolderPolicy
{
    /**
     * Folder Rahasia, termasuk semua isinya, hanya terlihat oleh Dewan Pengawas dan
     * auditor yang diberi akses Rahasia.
     */
    public function view(User $user, Folder $folder): bool
    {
        if ($folder->assignment_id === null) {
            return $user->hasRole(RoleSlug::DewanPengawas)
                || ($user->hasRole(RoleSlug::AuditorSpi) && ! $folder->isWithinConfidential());
        }

        if (! Gate::forUser($user)->allows('view', $folder->assignment)) {
            return false;
        }

        return ! $folder->isWithinConfidential()
            || Gate::forUser($user)->allows('accessConfidential', $folder->assignment);
    }

    public function createSubfolder(User $user, Folder $parent): bool
    {
        return $this->canModify($user, $parent);
    }

    public function rename(User $user, Folder $folder): bool
    {
        return ! $folder->isProtected() && $this->canModify($user, $folder);
    }

    public function move(User $user, Folder $folder): bool
    {
        return ! $folder->isProtected() && $this->canModify($user, $folder);
    }

    /**
     * Memindahkan folder keluar dari induk Rahasia sama dengan menurunkan klasifikasinya,
     * karena isinya menjadi terlihat oleh auditor tanpa akses Rahasia; hanya Dewan Pengawas.
     */
    public function moveTo(User $user, Folder $folder, Folder $target): bool
    {
        if (! $this->move($user, $folder) || ! $this->createSubfolder($user, $target)) {
            return false;
        }

        $leavesConfidentialParent = $folder->classification !== Classification::Rahasia
            && $folder->isWithinConfidential()
            && ! $target->isWithinConfidential();

        return ! $leavesConfidentialParent || $user->hasRole(RoleSlug::DewanPengawas);
    }

    public function archive(User $user, Folder $folder): bool
    {
        return ! $folder->isProtected() && $this->canModify($user, $folder);
    }

    /**
     * Without a target label this answers whether the user may change the label at all.
     * Auditor tanpa akses Rahasia tidak bisa memberi label Rahasia, dan hanya Dewan Pengawas
     * yang bisa menurunkan label Rahasia.
     */
    public function changeLabel(User $user, Folder $folder, ?Classification $newClassification = null): bool
    {
        if (! $this->canModify($user, $folder)) {
            return false;
        }

        if ($newClassification === null) {
            return true;
        }

        return $this->canApplyLabel($user, $folder, $folder->classification, $newClassification);
    }

    /**
     * Whether the user may give a folder under the given parent the requested label.
     */
    public function applyLabelUnder(User $user, Folder $parent, Classification $newClassification): bool
    {
        return $this->canApplyLabel($user, $parent, $parent->classification, $newClassification);
    }

    private function canApplyLabel(User $user, Folder $folder, Classification $currentClassification, Classification $newClassification): bool
    {
        if ($newClassification === Classification::Rahasia && ! $this->accessConfidential($user, $folder)) {
            return false;
        }

        return ! $currentClassification->isLoweredFromConfidential($newClassification)
            || $user->hasRole(RoleSlug::DewanPengawas);
    }

    /**
     * Regulasi dan Pedoman serta Templat: dilihat Dewan Pengawas dan Auditor SPI.
     */
    public function viewAnyGeneral(User $user): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas) || $user->hasRole(RoleSlug::AuditorSpi);
    }

    /**
     * Mengunggah berkas mengikuti aturan perubahan folder (US-09): di folder umum hanya Dewan Pengawas.
     */
    public function uploadFile(User $user, Folder $folder): bool
    {
        return $this->canModify($user, $folder);
    }

    /**
     * Whether the user may see content labelled Rahasia in this folder: Dewan Pengawas, or in an
     * assignment an auditor given confidential access.
     */
    public function accessConfidential(User $user, Folder $folder): bool
    {
        if ($folder->assignment_id === null) {
            return $user->hasRole(RoleSlug::DewanPengawas);
        }

        return Gate::forUser($user)->allows('accessConfidential', $folder->assignment);
    }

    /**
     * Folder terkunci, folder terarsip, dan penugasan yang ditutup menolak semua perubahan.
     * Folder umum di luar penugasan hanya dikelola Dewan Pengawas.
     */
    private function canModify(User $user, Folder $folder): bool
    {
        if ($folder->is_locked || $folder->isArchived() || ! $this->view($user, $folder)) {
            return false;
        }

        if ($folder->assignment_id === null) {
            return $user->hasRole(RoleSlug::DewanPengawas);
        }

        return Gate::forUser($user)->allows('manageFolders', $folder->assignment);
    }
}
