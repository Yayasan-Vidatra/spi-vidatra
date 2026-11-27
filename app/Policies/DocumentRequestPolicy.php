<?php

namespace App\Policies;

use App\Enums\DocumentRequestStatus;
use App\Enums\FolderPurpose;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Hak akses permintaan dokumen (US-15 sampai US-18, Rancangan Bagian 4): dibuat dan dipantau oleh Dewan
 * Pengawas dan auditor yang ditunjuk di penugasannya. Dicek di server di setiap permintaan.
 */
class DocumentRequestPolicy
{
    /**
     * H-07 untuk satu penugasan: siapa pun yang boleh melihat penugasannya.
     */
    public function viewAny(User $user, Assignment $assignment): bool
    {
        return Gate::forUser($user)->allows('view', $assignment);
    }

    public function view(User $user, DocumentRequest $documentRequest): bool
    {
        return Gate::forUser($user)->allows('view', $documentRequest->assignment);
    }

    /**
     * H-08 Permintaan untuk Unit Saya: PIC Unit yang sudah punya unit (US-16).
     */
    public function viewAnyForUnit(User $user): bool
    {
        return $user->hasRole(RoleSlug::PicUnit) && $user->unit_id !== null;
    }

    /**
     * PIC hanya melihat permintaan untuk unitnya sendiri (US-16).
     */
    public function viewForUnit(User $user, DocumentRequest $documentRequest): bool
    {
        return $this->viewAnyForUnit($user) && $user->unit_id === $documentRequest->unit_id;
    }

    /**
     * Ubah tenggat (US-18, keputusan 5): untuk sementara Dewan Pengawas dan auditor yang ditunjuk, dengan syarat
     * yang sama seperti membuat permintaan, selama permintaan masih Terbuka. Pengalihan ke Kepala SPI di Langkah 2b.
     */
    public function updateDueDate(User $user, DocumentRequest $documentRequest): bool
    {
        return $documentRequest->status === DocumentRequestStatus::Terbuka
            && $this->create($user, $documentRequest->assignment);
    }

    /**
     * Permintaan baru membuat subfolder di "02 Permintaan Dokumen", jadi pembuat harus boleh membuat
     * subfolder di sana: Dewan Pengawas atau auditor yang ditunjuk, selama penugasan belum ditutup dan
     * folder itu belum terkunci. Auditor tanpa akses Rahasia ditolak bila folder 02 berlabel Rahasia.
     */
    public function create(User $user, Assignment $assignment): bool
    {
        $requestsFolder = $assignment->standardFolder(FolderPurpose::PermintaanDokumen);

        return $requestsFolder !== null
            && Gate::forUser($user)->allows('createSubfolder', $requestsFolder);
    }
}
