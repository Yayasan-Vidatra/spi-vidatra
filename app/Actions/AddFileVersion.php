<?php

namespace App\Actions;

use App\Enums\ScanStatus;
use App\Jobs\ScanFileVersion;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;

/**
 * Menambah versi berikutnya ke berkas dan menjadikannya versi terkini (US-10). Versi baru selalu
 * menunggu pemindaian antivirus. Panggil di dalam transaksi setelah baris berkas dikunci, agar dua
 * unggahan bersamaan tidak mendapat nomor versi yang sama.
 */
class AddFileVersion
{
    /**
     * @param  array{stored_name: string, storage_path: string, size: int, mime_type: string, sha256: string, is_encrypted: bool}  $content
     */
    public function handle(File $file, User $uploader, array $content, ?FileVersion $restoredFrom = null): FileVersion
    {
        $version = $file->versions()->make();
        $version->forceFill([
            ...$content,
            'version_number' => ((int) $file->versions()->max('version_number')) + 1,
            'scan_status' => ScanStatus::Menunggu,
            'restored_from_version_id' => $restoredFrom?->id,
            'uploaded_by' => $uploader->id,
        ])->save();

        $file->forceFill(['current_version_id' => $version->id])->save();
        $file->setRelation('currentVersion', $version);

        ScanFileVersion::dispatch($version)->afterCommit();

        return $version;
    }
}
