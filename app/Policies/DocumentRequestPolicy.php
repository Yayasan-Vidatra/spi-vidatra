<?php

namespace App\Policies;

use App\Enums\FolderPurpose;
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
