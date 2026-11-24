<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\File;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Memulihkan berkas dari Tempat Sampah ke foldernya (US-13). Jika sementara itu sudah ada berkas aktif
 * bernama sama, berkas dipulihkan dengan akhiran "(dipulihkan YYYY-MM-DD)".
 */
class RestoreTrashedFile
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(File $file): File
    {
        return DB::transaction(function () use ($file): File {
            $locked = File::onlyTrashed()->lockForUpdate()->findOrFail($file->id);
            $originalName = $locked->original_name;
            $restoredName = $this->availableName($locked);

            $locked->forceFill([
                'original_name' => $restoredName,
                'deleted_at' => null,
                'deleted_by' => null,
            ])->save();

            $this->auditLogger->record(AuditAction::FileRestored, $locked, [
                'name' => $originalName,
                'restored_as' => $restoredName,
                'folder_id' => $locked->folder_id,
            ]);

            return $locked;
        });
    }

    private function availableName(File $file): string
    {
        $isTaken = fn (string $name): bool => File::query()
            ->where('folder_id', $file->folder_id)
            ->where('original_name', $name)
            ->exists();

        if (! $isTaken($file->original_name)) {
            return $file->original_name;
        }

        $extension = pathinfo($file->original_name, PATHINFO_EXTENSION);
        $baseName = $extension === '' ? $file->original_name : Str::beforeLast($file->original_name, '.');
        $suffix = $extension === '' ? '' : '.'.$extension;
        $date = now()->toDateString();

        for ($attempt = 1; ; $attempt++) {
            $marker = $attempt === 1 ? "dipulihkan {$date}" : "dipulihkan {$date} {$attempt}";
            $name = "{$baseName} ({$marker}){$suffix}";

            if (! $isTaken($name)) {
                return $name;
            }
        }
    }
}
