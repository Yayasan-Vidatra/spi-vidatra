<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;

/**
 * Data daftar berkas per folder untuk H-05 dan halaman folder umum (US-09).
 */
class FolderFileList
{
    /**
     * The folder must already carry its parent chain (and assignment) so the policy can check Rahasia
     * without extra queries.
     *
     * @return array{id: int, name: string, is_locked: bool, can: array{upload: bool}, files: list<array<string, mixed>>}
     */
    public function forFolder(Folder $folder, User $user): array
    {
        $files = File::query()
            ->where('folder_id', $folder->id)
            ->with(['currentVersion.uploader', 'versions:id,file_id,is_encrypted', ...File::EvidenceSourceRelations])
            ->orderBy('original_name')
            ->get()
            ->each(fn (File $file) => $file->setRelation('folder', $folder))
            ->filter(fn (File $file): bool => $user->can('view', $file));

        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'is_locked' => $folder->is_locked || $folder->isArchived(),
            'can' => ['upload' => $user->can('uploadFile', $folder)],
            'files' => $files->map(fn (File $file): array => [
                'id' => $file->id,
                'name' => $file->original_name,
                'classification' => $file->classification->value,
                'classification_label' => $file->classification->label(),
                'version_number' => $file->currentVersion->version_number,
                'size' => $file->currentVersion->size,
                'scan_status' => $file->currentVersion->scan_status->value,
                'scan_status_label' => $file->currentVersion->scan_status->label(),
                'uploader' => $file->currentVersion->uploader->name,
                'uploaded_at' => $file->currentVersion->created_at->toIso8601String(),
                'provenance' => $file->evidenceSource?->evidenceProvenance(),
                'encryption_status' => $file->encryptionSyncStatus()?->value,
                'encryption_status_label' => $file->encryptionSyncStatus()?->label(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{max_size_mb: int, max_files: int, allowed_extensions: list<string>}
     */
    public function uploadRules(): array
    {
        return [
            'max_size_mb' => config('berkas.max_size_mb'),
            'max_files' => config('berkas.max_files_per_upload'),
            'allowed_extensions' => config('berkas.allowed_extensions'),
        ];
    }

    /**
     * The page where the folder's files are listed.
     */
    public function folderUrl(Folder $folder): string
    {
        return $folder->assignment_id === null
            ? route('folder-umum.index', ['folder' => $folder->id])
            : route('penugasan.show', ['assignment' => $folder->assignment_id, 'folder' => $folder->id]);
    }
}
