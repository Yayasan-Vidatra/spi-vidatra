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
     * Terlambat (KF-08): the unit still owes an upload and the request's deadline has passed.
     */
    public function isOverdue(?CarbonInterface $now = null): bool
    {
        return $this->status->awaitsUpload() && $this->documentRequest->isPastDue($now);
    }
}
