<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Encryption\ConfidentialFileEncryption;
use App\Services\FileVersionContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Memulihkan versi lama dengan menyalin isinya menjadi versi baru (US-10). Riwayat versi tidak diubah.
 */
class RestoreFileVersion
{
    public function __construct(
        private AddFileVersion $addFileVersion,
        private AuditLogger $auditLogger,
        private FileVersionContent $fileContent,
        private ConfidentialFileEncryption $encryption,
    ) {}

    /**
     * @throws ValidationException when the content is or must become encrypted and no key is installed
     */
    public function handle(User $user, File $file, FileVersion $source): FileVersion
    {
        $encrypt = $file->requiresEncryption();

        if ($encrypt || $source->is_encrypted) {
            $this->encryption->ensureKey('version');
        }

        $disk = $this->fileContent->disk();
        $storedName = (string) Str::uuid();
        $storagePath = 'versi/'.$storedName;

        // Ditulis ulang, bukan disalin, agar enkripsinya mengikuti label berkas saat ini.
        $this->fileContent->write($storagePath, $this->fileContent->read($source), $encrypt);

        try {
            return DB::transaction(function () use ($user, $file, $source, $storedName, $storagePath, $encrypt): FileVersion {
                $lockedFile = File::query()->lockForUpdate()->findOrFail($file->id);
                $lockedFile->setRelation('folder', $file->folder);

                $version = $this->addFileVersion->handle($lockedFile, $user, [
                    'stored_name' => $storedName,
                    'storage_path' => $storagePath,
                    'size' => $source->size,
                    'mime_type' => $source->mime_type,
                    'sha256' => $source->sha256,
                    'is_encrypted' => $encrypt,
                ], $source);

                $this->auditLogger->record(AuditAction::FileVersionRestored, $lockedFile, [
                    'version' => $version->version_number,
                    'version_id' => $version->id,
                    'restored_from_version' => $source->version_number,
                    'restored_from_version_id' => $source->id,
                    'sha256' => $version->sha256,
                ], $user);

                return $version;
            });
        } catch (Throwable $exception) {
            $disk->delete($storagePath);

            throw $exception;
        }
    }
}
