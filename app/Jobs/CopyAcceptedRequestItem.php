<?php

namespace App\Jobs;

use App\Actions\AddFileVersion;
use App\Enums\AuditAction;
use App\Enums\FolderPurpose;
use App\Models\File;
use App\Models\Folder;
use App\Models\RequestItem;
use App\Services\AuditLogger;
use App\Services\Encryption\FileCipher;
use App\Services\FileVersionContent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Menyalin versi slot yang diterima ke "04 Bukti Unit" penugasannya sebagai berkas baru versi 1 (US-17),
 * bernama "<kode unit> - <nama dokumen>.<ext>" dengan akhiran " (2)", " (3)" bila nama itu sudah dipakai.
 * Label salinan mengikuti folder 04, isinya dienkripsi bila perlu, lalu dipindai ulang lewat alur biasa.
 * Aman dijalankan berulang: slot yang sudah punya salinan tidak disalin lagi. Selama belum berhasil, slot
 * tetap Diterima dengan keterangan "salinan ke 04 tertunda".
 */
class CopyAcceptedRequestItem implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600, 1800];

    public int $timeout = 60;

    public function __construct(public RequestItem $item) {}

    public function handle(FileVersionContent $content, AddFileVersion $addFileVersion, AuditLogger $auditLogger, FileCipher $cipher): void
    {
        $item = RequestItem::query()
            ->with(['documentRequest.assignment', 'documentRequest.unit', 'file', 'acceptedVersion', 'reviewer'])
            ->find($this->item->id);

        if ($item === null || ! $item->isEvidenceCopyPending()) {
            return;
        }

        $folder = $item->documentRequest->assignment->standardFolder(FolderPurpose::BuktiUnit)
            ?? throw new RuntimeException('Penugasan ini tidak punya folder 04 Bukti Unit.');
        $source = $item->acceptedVersion
            ?? throw new RuntimeException('Slot Diterima tanpa versi yang diterima.');

        // Salinan mewarisi label folder 04 (US-07); enkripsinya mengikuti label itu (US-12).
        $file = (new File)->forceFill([
            'folder_id' => $folder->id,
            'classification' => $folder->classification,
            'created_by' => $item->reviewed_by,
        ]);
        $file->setRelation('folder', $folder);
        $encrypt = $file->requiresEncryption();

        if ($encrypt && ! $cipher->isAvailable()) {
            throw new RuntimeException('Kunci enkripsi berkas Rahasia belum terpasang; salinan ke 04 Bukti Unit belum bisa dibuat.');
        }

        $storedName = (string) Str::uuid();
        $storagePath = 'versi/'.$storedName;
        $content->write($storagePath, $content->read($source), $encrypt);

        $copyContent = [
            'stored_name' => $storedName,
            'storage_path' => $storagePath,
            'size' => $source->size,
            'mime_type' => $source->mime_type,
            'sha256' => $source->sha256,
            'is_encrypted' => $encrypt,
        ];

        try {
            $copied = DB::transaction(fn (): bool => $this->store($item, $file, $folder, $copyContent, $addFileVersion, $auditLogger));
        } catch (Throwable $exception) {
            $content->disk()->delete($storagePath);

            throw $exception;
        }

        if (! $copied) {
            $content->disk()->delete($storagePath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Penyalinan slot yang diterima ke 04 Bukti Unit gagal; slot tetap Diterima dengan salinan tertunda.', [
            'request_item_id' => $this->item->id,
            'exception' => $exception,
        ]);
    }

    /**
     * @param  array{stored_name: string, storage_path: string, size: int, mime_type: string, sha256: string, is_encrypted: bool}  $content
     */
    private function store(RequestItem $item, File $file, Folder $folder, array $content, AddFileVersion $addFileVersion, AuditLogger $auditLogger): bool
    {
        $locked = RequestItem::query()->lockForUpdate()->findOrFail($item->id);

        // Job yang berjalan dua kali: salinan sudah dibuat oleh percobaan lain.
        if (! $locked->isEvidenceCopyPending()) {
            return false;
        }

        // Kunci folder 04 agar dua salinan bersamaan tidak memilih nama yang sama.
        Folder::query()->lockForUpdate()->findOrFail($folder->id);

        $name = $this->availableName($folder, $item->evidenceFileName());
        $file->forceFill(['original_name' => $name])->save();
        $version = $addFileVersion->handle($file, $item->reviewer, $content);

        $locked->forceFill([
            'evidence_file_id' => $file->id,
            'evidence_copy_pending_since' => null,
        ])->save();

        $auditLogger->record(AuditAction::FileUploaded, $file, [
            'name' => $name,
            'folder_id' => $folder->id,
            'version' => $version->version_number,
            'version_id' => $version->id,
            'size' => $version->size,
            'mime_type' => $version->mime_type,
            'sha256' => $version->sha256,
            'encrypted' => $version->is_encrypted,
            'request_item_id' => $item->id,
            'copied_from_version_id' => $item->accepted_version_id,
        ]);

        $auditLogger->record(AuditAction::RequestItemCopiedToEvidence, $locked, [
            'document_request_id' => $item->document_request_id,
            'name' => $item->name,
            'source_file_id' => $item->file_id,
            'source_version_id' => $item->accepted_version_id,
            'evidence_file_id' => $file->id,
            'evidence_name' => $name,
            'folder_id' => $folder->id,
        ]);

        return true;
    }

    /**
     * The base name, or the first of "<name> (2).<ext>", "<name> (3).<ext>", … not used by an active file in
     * the folder. Files in the trash do not hold their name (US-13).
     */
    private function availableName(Folder $folder, string $baseName): string
    {
        $stem = pathinfo($baseName, PATHINFO_FILENAME);
        $extension = pathinfo($baseName, PATHINFO_EXTENSION);
        $candidate = $baseName;

        for ($suffix = 2; File::query()->where('folder_id', $folder->id)->where('original_name', $candidate)->exists(); $suffix++) {
            $candidate = "{$stem} ({$suffix}).{$extension}";
        }

        return $candidate;
    }
}
