<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\File;
use App\Models\FileVersion;
use App\Services\AuditLogger;
use App\Services\FileVersionContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menghapus permanen berkas dari Tempat Sampah (US-13): baris berkas dan versinya serta isinya di disk,
 * termasuk yang dikarantina. Log audit tetap utuh.
 */
class PurgeFile
{
    public function __construct(
        private AuditLogger $auditLogger,
        private FileVersionContent $fileContent,
    ) {}

    /**
     * @param  bool  $isAutomatic  true when run by the scheduler after the retention period
     */
    public function handle(File $file, bool $isAutomatic = false): void
    {
        $paths = DB::transaction(function () use ($file, $isAutomatic): array {
            $locked = File::onlyTrashed()->lockForUpdate()->findOrFail($file->id);
            $versions = FileVersion::query()->where('file_id', $locked->id);
            $paths = $versions->pluck('storage_path')->all();

            $locked->forceFill(['current_version_id' => null])->save();
            (clone $versions)->update(['restored_from_version_id' => null]);
            (clone $versions)->delete();
            $locked->forceDelete();

            $this->auditLogger->record(AuditAction::FilePurged, $locked, [
                'name' => $locked->original_name,
                'folder_id' => $locked->folder_id,
                'version_count' => count($paths),
                'automatic' => $isAutomatic,
            ]);

            return $paths;
        });

        // Isi dihapus setelah commit; jika gagal, hanya tersisa isi tanpa baris yang tidak bisa diakses aplikasi.
        foreach ($paths as $path) {
            try {
                $this->fileContent->disk()->delete($path);
            } catch (Throwable $exception) {
                Log::warning('Isi berkas yang dihapus permanen gagal dihapus dari disk.', ['path' => $path, 'exception' => $exception]);
            }
        }
    }
}
