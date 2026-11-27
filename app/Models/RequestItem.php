<?php

namespace App\Models;

use App\Enums\RequestItemStatus;
use Carbon\CarbonInterface;
use Database\Factories\RequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Slot unggah untuk satu dokumen yang diminta (US-15 sampai US-17). Status, berkas slot, hasil periksa, dan
 * salinan di "04 Bukti Unit" diisi oleh aplikasi, bukan dari input pengguna.
 */
#[Fillable(['name', 'description', 'accepted_extensions'])]
class RequestItem extends Model
{
    /**
     * Catatan sistem saat unggahan terdeteksi virus (US-16); disimpan di system_note, bukan reviewer_note.
     */
    public const string InfectedSystemNote = 'Berkas terdeteksi virus dan dikarantina. Unggah ulang berkas yang bersih.';

    /** @use HasFactory<RequestItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_extensions' => 'array',
            'status' => RequestItemStatus::class,
            'sort_order' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'evidence_copy_pending_since' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DocumentRequest, $this>
     */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    /**
     * Berkas slot di subfolder permintaan; setiap unggahan ulang menjadi versi baru berkas ini.
     *
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Salinan versi yang diterima di "04 Bukti Unit".
     *
     * @return BelongsTo<File, $this>
     */
    public function evidenceFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'evidence_file_id');
    }

    /**
     * Versi berkas slot yang diterima auditor; hanya versi ini yang disalin ke "04 Bukti Unit" (US-17).
     *
     * @return BelongsTo<FileVersion, $this>
     */
    public function acceptedVersion(): BelongsTo
    {
        return $this->belongsTo(FileVersion::class, 'accepted_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Format groups an auditor may choose for a slot: those in config whose extensions are all allowed uploads.
     *
     * @return array<string, array{label: string, extensions: list<string>}>
     */
    public static function formatGroups(): array
    {
        $allowedExtensions = config('berkas.allowed_extensions');

        return array_filter(
            config('berkas.request_format_groups'),
            fn (array $group): bool => array_diff($group['extensions'], $allowedExtensions) === [],
        );
    }

    /**
     * @param  list<string>  $groupKeys
     * @return list<string>
     */
    public static function extensionsForGroups(array $groupKeys): array
    {
        $groups = self::formatGroups();

        return array_values(array_unique(array_merge(
            ...array_map(fn (string $key): array => $groups[$key]['extensions'], $groupKeys),
        )));
    }

    /**
     * Labels of the format groups this slot accepts, for display.
     *
     * @return list<string>
     */
    public function acceptedFormatLabels(): array
    {
        return array_values(array_map(
            fn (array $group): string => $group['label'],
            array_filter(
                config('berkas.request_format_groups'),
                fn (array $group): bool => array_intersect($group['extensions'], $this->accepted_extensions) !== [],
            ),
        ));
    }

    /**
     * The name of the slot file for an upload with the given extension: the document name, so every
     * re-upload stays one file whatever the unit named it, with the extension of the latest version.
     */
    public function slotFileName(string $extension): string
    {
        return $this->name.'.'.strtolower($extension);
    }

    /**
     * The name of the copy in "04 Bukti Unit" before any " (2)" suffix: the unit code and the document name,
     * with the extension of the accepted version's file.
     */
    public function evidenceFileName(): string
    {
        return $this->documentRequest->unit->code.' - '.$this->name.'.'.strtolower(pathinfo($this->file->original_name, PATHINFO_EXTENSION));
    }

    /**
     * Origin of the copy in "04 Bukti Unit" for H-05 and H-06, e.g. "Disalin dari Permintaan 02 · diunggah
     * <PIC> · diterima <auditor>". Needs documentRequest, acceptedVersion.uploader, and reviewer loaded.
     */
    public function evidenceProvenance(): string
    {
        return sprintf(
            'Disalin dari Permintaan %02d · diunggah %s · diterima %s',
            $this->documentRequest->sequence_number,
            $this->acceptedVersion->uploader->name,
            $this->reviewer->name,
        );
    }

    /**
     * Diterima, but the copy job has not placed the accepted version in "04 Bukti Unit" yet ("salinan ke 04 tertunda").
     */
    public function isEvidenceCopyPending(): bool
    {
        return $this->status === RequestItemStatus::Diterima && $this->evidence_file_id === null;
    }

    /**
     * Whether the latest upload arrived after the deadline ("diunggah terlambat").
     */
    public function isSubmittedLate(): bool
    {
        return $this->submitted_at !== null && $this->submitted_at->greaterThan($this->documentRequest->dueAt());
    }

    /**
     * Terlambat (KF-08): the unit still owes an upload and the request's deadline has passed.
     */
    public function isOverdue(?CarbonInterface $now = null): bool
    {
        return $this->status->awaitsUpload() && $this->documentRequest->isPastDue($now);
    }
}
