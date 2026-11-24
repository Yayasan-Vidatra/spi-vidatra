<?php

namespace App\Services\Encryption;

use App\Jobs\SyncFileEncryption;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Menjaga agar perubahan yang memindah berkas ke dalam atau keluar dari cakupan Rahasia (label berkas,
 * label folder, pindah folder) hanya terjadi jika kunci enkripsi terpasang, lalu menjadwalkan enkripsi
 * ulang. Tanpa kunci, perubahan ditolak di depan agar job enkripsi ulang tidak gagal di tengah.
 */
class ConfidentialFileEncryption
{
    public const string KeyMissingMessage = 'Kunci enkripsi berkas Rahasia belum terpasang di server. Hubungi Admin IT.';

    public function __construct(private FileCipher $cipher) {}

    /**
     * Reject the change when it would encrypt or decrypt stored content and no key is installed.
     *
     * @throws ValidationException
     */
    public function ensureKeyForFileChange(File $file, bool $willRequireEncryption, string $field): void
    {
        if ($file->requiresEncryption() !== $willRequireEncryption && $file->versions()->exists()) {
            $this->ensureKey($field);
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureKeyForFolderChange(Folder $folder, bool $willBeConfidential, string $field): void
    {
        if ($folder->isWithinConfidential() !== $willBeConfidential && $this->filesBeneath($folder)->exists()) {
            $this->ensureKey($field);
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureKey(string $field): void
    {
        if (! $this->cipher->isAvailable()) {
            throw ValidationException::withMessages([$field => self::KeyMissingMessage]);
        }
    }

    /**
     * Schedule the re-encryption and remember since when it is pending, so that berkas:periksa-enkripsi
     * can reschedule it when a worker never picks it up. A file already pending keeps its original time.
     */
    public function syncFile(File $file): void
    {
        File::withTrashed()
            ->whereKey($file->id)
            ->whereNull('encryption_pending_since')
            ->toBase()
            ->update(['encryption_pending_since' => now()]);

        SyncFileEncryption::dispatch($file)->afterCommit();
    }

    /**
     * Schedule a sync for every file in the folder and beneath it, including files in the trash.
     * The job does nothing for files whose content already matches their label.
     */
    public function syncFolder(Folder $folder): void
    {
        $this->filesBeneath($folder)->each(fn (File $file) => $this->syncFile($file));
    }

    /**
     * @return Builder<File>
     */
    private function filesBeneath(Folder $folder): Builder
    {
        return File::withTrashed()->whereIn('folder_id', $folder->selfAndDescendants()->pluck('id'));
    }
}
