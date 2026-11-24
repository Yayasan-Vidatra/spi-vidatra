<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Encryption\ConfidentialFileEncryption;
use App\Services\FileTypeDetector;
use App\Services\FileVersionContent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * Menyimpan satu berkas unggahan (US-09). Berkas dengan nama sama di folder yang sama menjadi versi
 * berikutnya (US-10). Isi disimpan dengan nama UUID di disk privat; nama asli hanya di basis data.
 * Isi Rahasia disimpan terenkripsi (US-12).
 */
class StoreFileUpload
{
    private const int MaxAttempts = 2;

    /**
     * Pesan saat nama sudah dipakai berkas yang tidak boleh diperbarui pengunggah. Sengaja umum agar
     * tidak mengungkap keberadaan berkas yang tidak bisa ia lihat, misalnya berkas Rahasia.
     */
    public const string BlockedNameMessage = 'Berkas ini tidak dapat diunggah ke folder ini. Ganti nama berkas lalu coba lagi.';

    public function __construct(
        private AddFileVersion $addFileVersion,
        private AuditLogger $auditLogger,
        private FileTypeDetector $detector,
        private FileVersionContent $fileContent,
        private ConfidentialFileEncryption $encryption,
    ) {}

    public function handle(User $uploader, Folder $folder, UploadedFile $upload): FileVersion
    {
        $name = File::normalizeName($upload->getClientOriginalName());
        $mimeType = $this->detector->detect($upload->getRealPath(), pathinfo($name, PATHINFO_EXTENSION))
            ?? throw new LogicException('Berkas harus sudah lolos validasi AllowedFileContent.');

        // Isi Rahasia dienkripsi sebelum menyentuh disk berkas (US-12); tanpa kunci, unggahan ditolak.
        $encrypt = $this->willBeEncrypted($folder, $name);

        if ($encrypt) {
            $this->encryption->ensureKey('files');
        }

        $storedName = (string) Str::uuid();
        $storagePath = 'versi/'.$storedName;
        $this->fileContent->write($storagePath, $this->fileContent->readLocalFile($upload->getRealPath()), $encrypt);

        $content = [
            'stored_name' => $storedName,
            'storage_path' => $storagePath,
            'size' => filesize($upload->getRealPath()),
            'mime_type' => $mimeType,
            'sha256' => hash_file('sha256', $upload->getRealPath()),
            'is_encrypted' => $encrypt,
        ];
        $disk = $this->fileContent->disk();

        try {
            return $this->storeWithRetry($uploader, $folder, $name, $content);
        } catch (Throwable $exception) {
            $disk->delete($storagePath);

            throw $exception;
        }
    }

    /**
     * Whether an upload with this name into the folder is stored encrypted: the folder lies within a
     * Rahasia folder, or the upload becomes a new version of a file labelled Rahasia.
     */
    public function willBeEncrypted(Folder $folder, string $name): bool
    {
        if ($folder->isWithinConfidential()) {
            return true;
        }

        return File::query()
            ->where('folder_id', $folder->id)
            ->where('original_name', $name)
            ->where('classification', Classification::Rahasia)
            ->exists();
    }

    /**
     * @param  array{stored_name: string, storage_path: string, size: int, mime_type: string, sha256: string, is_encrypted: bool}  $content
     */
    private function storeWithRetry(User $uploader, Folder $folder, string $name, array $content): FileVersion
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): FileVersion => $this->store($uploader, $folder, $name, $content));
            } catch (UniqueConstraintViolationException $exception) {
                // Unggahan lain dengan nama yang sama membuat berkasnya lebih dulu; ulangi sebagai versi baru.
                if ($attempt >= self::MaxAttempts) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array{stored_name: string, storage_path: string, size: int, mime_type: string, sha256: string, is_encrypted: bool}  $content
     */
    private function store(User $uploader, Folder $folder, string $name, array $content): FileVersion
    {
        $file = File::query()
            ->where('folder_id', $folder->id)
            ->where('original_name', $name)
            ->lockForUpdate()
            ->first();

        $isNewFile = $file === null;

        if ($isNewFile) {
            // Berkas baru mewarisi label folder; label bisa diubah sesudahnya (US-07).
            $file = (new File)->forceFill([
                'folder_id' => $folder->id,
                'original_name' => $name,
                'classification' => $folder->classification,
                'created_by' => $uploader->id,
            ]);
            $file->save();
        }

        $file->setRelation('folder', $folder);

        if (! $isNewFile && Gate::forUser($uploader)->denies('uploadVersion', $file)) {
            throw ValidationException::withMessages([
                'files' => self::BlockedNameMessage,
            ]);
        }

        $version = $this->addFileVersion->handle($file, $uploader, $content);

        // Label berubah di antara pengecekan dan penguncian: samakan enkripsinya setelah commit.
        if ($content['is_encrypted'] !== $file->requiresEncryption()) {
            $this->encryption->syncFile($file);
        }

        $this->auditLogger->record($isNewFile ? AuditAction::FileUploaded : AuditAction::FileVersionAdded, $file, [
            'name' => $name,
            'folder_id' => $folder->id,
            'version' => $version->version_number,
            'version_id' => $version->id,
            'size' => $version->size,
            'mime_type' => $version->mime_type,
            'sha256' => $version->sha256,
            'encrypted' => $version->is_encrypted,
        ], $uploader);

        return $version;
    }
}
