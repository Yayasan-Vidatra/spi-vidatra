<?php

namespace App\Models;

use App\Enums\Classification;
use Database\Factories\FolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Folder penugasan atau folder umum di luar penugasan (US-07). Semua kolom diisi eksplisit
 * oleh aplikasi; label, kunci, dan induk tidak boleh diatur lewat mass assignment.
 */
class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => Classification::class,
            'is_locked' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<FolderTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(FolderTemplate::class, 'folder_template_id');
    }

    /**
     * Permintaan dokumen yang memakai folder ini sebagai subfolder slot (US-15).
     *
     * @return HasOne<DocumentRequest, $this>
     */
    public function documentRequest(): HasOne
    {
        return $this->hasOne(DocumentRequest::class);
    }

    /**
     * Folder baku penugasan, folder umum yang dibuat seeder, dan subfolder permintaan dokumen tidak bisa
     * diubah nama, dipindah, atau diarsipkan, karena modul lain bergantung padanya.
     */
    public function isProtected(): bool
    {
        return $this->folder_template_id !== null
            || ($this->assignment_id === null && $this->parent_id === null)
            || $this->isRequestFolder();
    }

    /**
     * Subfolder permintaan dokumen hanya diisi lewat slot unggah (US-16), bukan lewat H-05.
     */
    public function isRequestFolder(): bool
    {
        return $this->assignment_id !== null && $this->documentRequest !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Whether this folder or any of its ancestors is labelled Rahasia.
     */
    public function isWithinConfidential(): bool
    {
        for ($folder = $this; $folder !== null; $folder = $folder->parent) {
            if ($folder->classification === Classification::Rahasia) {
                return true;
            }
        }

        return false;
    }

    /**
     * Subquery of the ids of every folder labelled Rahasia and every folder beneath one, the SQL
     * counterpart of isWithinConfidential().
     */
    public static function confidentialFolderIdsSql(): string
    {
        return sprintf(
            "(with recursive confidential_folders (id) as (select id from folders where classification = '%s'"
            .' union all select folders.id from folders inner join confidential_folders on folders.parent_id = confidential_folders.id)'
            .' select id from confidential_folders)',
            Classification::Rahasia->value,
        );
    }

    /**
     * Whether this folder is the given folder or lies somewhere beneath it.
     */
    public function isSameOrDescendantOf(self $folder): bool
    {
        for ($current = $this; $current !== null; $current = $current->parent) {
            if ($current->is($folder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect this folder and every folder beneath it.
     *
     * @return Collection<int, self>
     */
    public function selfAndDescendants(): Collection
    {
        $folders = collect([$this]);

        foreach ($this->children()->get() as $child) {
            $folders = $folders->merge($child->selfAndDescendants());
        }

        return $folders;
    }
}
