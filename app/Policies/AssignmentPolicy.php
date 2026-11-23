<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\User;

/**
 * Hak akses penugasan sesuai Rancangan Bagian 4. Super Admin, Pengurus Yayasan,
 * PIC Unit, dan Peninjau Tamu belum punya akses ke penugasan di M2.
 */
class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas) || $user->hasRole(RoleSlug::AuditorSpi);
    }

    /**
     * Auditor hanya melihat penugasan tempat ia ditunjuk (US-06).
     */
    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->hasRole(RoleSlug::DewanPengawas)) {
            return true;
        }

        return $user->hasRole(RoleSlug::AuditorSpi) && $assignment->memberFor($user) !== null;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas);
    }

    public function close(User $user, Assignment $assignment): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas) && ! $assignment->isClosed();
    }

    /**
     * Hanya Dewan Pengawas yang bisa membuka kembali penugasan yang ditutup (US-08).
     */
    public function reopen(User $user, Assignment $assignment): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas) && $assignment->isClosed();
    }

    /**
     * Menunjuk, mencabut, dan mengatur akses Rahasia auditor (US-06, US-07).
     */
    public function manageMembers(User $user, Assignment $assignment): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas) && ! $assignment->isClosed();
    }

    /**
     * Dewan Pengawas melihat semua folder Rahasia; auditor hanya jika diberi akses oleh Dewan Pengawas.
     */
    public function accessConfidential(User $user, Assignment $assignment): bool
    {
        if ($user->hasRole(RoleSlug::DewanPengawas)) {
            return true;
        }

        return $user->hasRole(RoleSlug::AuditorSpi)
            && (bool) $assignment->memberFor($user)?->can_access_confidential;
    }

    /**
     * Mengelola folder di dalam penugasan: Dewan Pengawas dan auditor yang ditunjuk,
     * selama penugasan belum ditutup.
     */
    public function manageFolders(User $user, Assignment $assignment): bool
    {
        return ! $assignment->isClosed() && $this->view($user, $assignment);
    }
}
