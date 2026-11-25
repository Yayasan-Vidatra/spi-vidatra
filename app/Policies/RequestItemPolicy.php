<?php

namespace App\Policies;

use App\Enums\RequestItemStatus;
use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Hak atas slot unggah permintaan dokumen (US-16, US-17). Dicek di server di setiap permintaan.
 */
class RequestItemPolicy
{
    /**
     * PIC unit tujuan mengunggah ke slot selama slot belum Diterima dan penugasan belum ditutup. Unggahan
     * setelah tenggat tetap diterima dan ditandai terlambat. Unggahan saat Diunggah atau Perlu Perbaikan
     * menjadi versi baru (US-17); slot Diterima terkunci untuk PIC.
     */
    public function upload(User $user, RequestItem $item): bool
    {
        $documentRequest = $item->documentRequest;

        return Gate::forUser($user)->allows('viewForUnit', $documentRequest)
            && $item->status !== RequestItemStatus::Diterima
            && ! $documentRequest->assignment->isClosed();
    }

    /**
     * Memeriksa slot (US-17, UC-08): Dewan Pengawas dan auditor yang ditunjuk di penugasannya, selama
     * penugasan belum ditutup, untuk slot Diunggah yang berkasnya boleh ia buka (berkas Rahasia butuh akses
     * Rahasia). PIC Unit, Pengurus Yayasan, Super Admin, dan Peninjau Tamu tidak bisa memeriksa. Berkas yang
     * belum dinyatakan bersih oleh pemindai ditolak saat hasil periksa disimpan.
     */
    public function review(User $user, RequestItem $item): bool
    {
        $documentRequest = $item->documentRequest;

        return $item->status === RequestItemStatus::Diunggah
            && $item->file !== null
            && ! $documentRequest->assignment->isClosed()
            && Gate::forUser($user)->allows('view', $documentRequest)
            && Gate::forUser($user)->allows('view', $item->file);
    }
}
