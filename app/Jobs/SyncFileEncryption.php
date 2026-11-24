<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Models\File;
use App\Models\FileVersion;
use App\Services\AuditLogger;
use App\Services\FileVersionContent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menyamakan enkripsi semua versi berkas dengan labelnya (US-12): dienkripsi jika berkas berlabel
 * Rahasia atau berada di bawah folder Rahasia, didekripsi jika tidak lagi. Isi baru ditulis ke lokasi
 * baru dulu, lokasi di basis data diganti, lalu isi lama dihapus, sehingga unduhan yang sedang berjalan
 * tidak terputus. Aman dijalankan berulang.
 */
class SyncFileEncryption implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600, 1800];

    public int $timeout = 60;

    public function __construct(public File $file) {}

    public function handle(FileVersionContent $content, AuditLogger $auditLogger): void
    {
        $file = File::withTrashed()->with('folder')->find($this->file->id);

        if ($file === null) {
            return;
        }

        foreach ($file->versions()->orderBy('version_number')->pluck('id') as $versionId) {
            $this->syncVersion($file, $versionId, $content, $auditLogger);
        }

        // Dibaca ulang karena label bisa berubah lagi selama job berjalan; penanda hanya dihapus jika semua versi sudah sesuai.
        $current = File::withTrashed()->with(['folder', 'versions:id,file_id,is_encrypted'])->find($file->id);

        if ($current !== null && $current->encryptionSyncStatus() === null) {
            File::withTrashed()->whereKey($file->id)->toBase()->update(['encryption_pending_since' => null]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Enkripsi ulang berkas gagal; sebagian versi belum sesuai labelnya.', [
            'file_id' => $this->file->id,
            'exception' => $exception,
        ]);
    }

    private function syncVersion(File $file, int $versionId, FileVersionContent $content, AuditLogger $auditLogger): void
    {
        $shouldEncrypt = $file->requiresEncryption();
        $version = FileVersion::query()->find($versionId);

        if ($version === null || $version->is_encrypted === $shouldEncrypt) {
            return;
        }

        $oldPath = $version->storage_path;
        $newStoredName = (string) Str::uuid();
        $newPath = Str::beforeLast($oldPath, '/').'/'.$newStoredName;

        $content->write($newPath, $content->read($version), $shouldEncrypt);

        try {
            $changed = DB::transaction(function () use ($file, $versionId, $oldPath, $newStoredName, $newPath, $shouldEncrypt, $auditLogger): bool {
                $locked = FileVersion::query()->lockForUpdate()->findOrFail($versionId);

                // Lokasi berubah sejak dibaca (misalnya dikarantina): ulangi di percobaan berikutnya.
                if ($locked->storage_path !== $oldPath) {
                    return false;
                }

                $locked->forceFill([
                    'stored_name' => $newStoredName,
                    'storage_path' => $newPath,
                    'is_encrypted' => $shouldEncrypt,
                ])->save();

                $auditLogger->record($shouldEncrypt ? AuditAction::FileEncrypted : AuditAction::FileDecrypted, $file, [
                    'version' => $locked->version_number,
                    'version_id' => $locked->id,
                ]);

                return true;
            });
        } catch (Throwable $exception) {
            $content->disk()->delete($newPath);

            throw $exception;
        }

        $content->disk()->delete($changed ? $oldPath : $newPath);

        if (! $changed) {
            $this->release(30);
        }
    }
}
