<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Enums\RequestItemStatus;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\RequestItem;
use App\Models\User;
use App\Notifications\RequestItemUploaded;
use App\Services\AuditLogger;
use App\Services\Encryption\ConfidentialFileEncryption;
use App\Services\FileTypeDetector;
use App\Services\FileVersionContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * PIC mengunggah satu berkas ke slot permintaan (US-16) lewat alur unggah M3: isi disimpan dengan nama UUID,
 * dienkripsi bila Rahasia (US-12), dan setiap versi dipindai antivirus. Berkas slot dinamai menurut nama
 * dokumen; unggahan ulang menjadi versi baru berkas yang sama (US-17). Slot menjadi Diunggah dan pembuat
 * permintaan diberi tahu setelah commit.
 */
class StoreRequestItemUpload
{
    public const string LockedMessage = 'Slot ini sudah Diterima atau penugasannya sudah ditutup, sehingga tidak bisa diunggah lagi.';

    public function __construct(
        private AddFileVersion $addFileVersion,
        private AuditLogger $auditLogger,
        private FileTypeDetector $detector,
        private FileVersionContent $fileContent,
        private ConfidentialFileEncryption $encryption,
    ) {}

    public function handle(User $uploader, RequestItem $item, UploadedFile $upload): FileVersion
    {
        $extension = strtolower(pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION));
        $mimeType = $this->detector->detect($upload->getRealPath(), $extension)
            ?? throw new LogicException('Berkas harus sudah lolos validasi AllowedFileContent.');
        $folder = $item->documentRequest->folder;

        $encrypt = $this->willBeEncrypted($item);

        if ($encrypt) {
            $this->encryption->ensureKey('file');
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

        try {
            $version = DB::transaction(fn (): FileVersion => $this->store($uploader, $item, $item->slotFileName($extension), $content));
        } catch (Throwable $exception) {
            $this->fileContent->disk()->delete($storagePath);

            throw $exception;
        }

        $item->refresh();
        $creator = $item->documentRequest->creator;

        if ($creator->is_active) {
            $creator->notify(new RequestItemUploaded($item, $uploader));
        }

        return $version;
    }

    /**
     * Isi slot dienkripsi bila subfolder permintaan berada di bawah folder Rahasia, atau berkas slot sudah
     * berlabel Rahasia.
     */
    public function willBeEncrypted(RequestItem $item): bool
    {
        return $item->documentRequest->folder->isWithinConfidential()
            || $item->file?->classification === Classification::Rahasia;
    }

    /**
     * @param  array{stored_name: string, storage_path: string, size: int, mime_type: string, sha256: string, is_encrypted: bool}  $content
     */
    private function store(User $uploader, RequestItem $item, string $name, array $content): FileVersion
    {
        // Kunci slot agar unggahan bersamaan dan penerimaan oleh auditor tidak saling menimpa.
        $item = RequestItem::query()->lockForUpdate()->findOrFail($item->id);
        $documentRequest = $item->documentRequest;
        $folder = $documentRequest->folder;

        if (Gate::forUser($uploader)->denies('upload', $item)) {
            throw ValidationException::withMessages(['file' => self::LockedMessage]);
        }

        $file = $item->file_id === null ? null : File::query()->lockForUpdate()->findOrFail($item->file_id);
        $isNewFile = $file === null;

        if ($isNewFile) {
            // Berkas slot mewarisi label subfolder permintaan (US-07).
            $file = (new File)->forceFill([
                'folder_id' => $folder->id,
                'original_name' => $name,
                'classification' => $folder->classification,
                'created_by' => $uploader->id,
            ]);
            $file->save();
        } elseif ($file->original_name !== $name) {
            // Unggahan ulang dengan ekstensi lain: nama berkas mengikuti versi terbaru.
            $file->forceFill(['original_name' => $name])->save();
        }

        $file->setRelation('folder', $folder);
        $version = $this->addFileVersion->handle($file, $uploader, $content);

        // Label berubah di antara pengecekan dan penguncian: samakan enkripsinya setelah commit.
        if ($content['is_encrypted'] !== $file->requiresEncryption()) {
            $this->encryption->syncFile($file);
        }

        $item->forceFill([
            'file_id' => $file->id,
            'status' => RequestItemStatus::Diunggah,
            'submitted_at' => now(),
            // Catatan sistem (misalnya berkas terinfeksi) tidak berlaku lagi untuk unggahan baru.
            'system_note' => null,
        ])->save();

        $this->auditLogger->record($isNewFile ? AuditAction::FileUploaded : AuditAction::FileVersionAdded, $file, [
            'name' => $name,
            'folder_id' => $folder->id,
            'version' => $version->version_number,
            'version_id' => $version->id,
            'size' => $version->size,
            'mime_type' => $version->mime_type,
            'sha256' => $version->sha256,
            'encrypted' => $version->is_encrypted,
            'request_item_id' => $item->id,
        ], $uploader);

        $this->auditLogger->record(AuditAction::RequestItemUploaded, $item, [
            'document_request_id' => $documentRequest->id,
            'unit' => $documentRequest->unit->code,
            'name' => $item->name,
            'file_id' => $file->id,
            'version' => $version->version_number,
            'version_id' => $version->id,
            'late' => $item->isSubmittedLate(),
        ], $uploader);

        return $version;
    }
}
