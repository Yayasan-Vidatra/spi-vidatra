<?php

namespace App\Models;

use App\Enums\Classification;
use App\Enums\EncryptionSyncStatus;
use App\Enums\RoleSlug;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

/**
 * Berkas di sebuah folder (US-09). Isi, ukuran, hash, dan status pindai ada per versi di FileVersion.
 * Berkas yang dihapus masuk Tempat Sampah lewat soft delete (US-13). Semua kolom diisi eksplisit oleh
 * aplikasi; tidak ada mass assignment.
 */
class File extends Model
{
    /**
     * Relasi yang dimuat bersama berkas agar asal-usul salinan di "04 Bukti Unit" bisa ditampilkan tanpa N+1.
     *
     * @var list<string>
     */
    public const array EvidenceSourceRelations = [
        'evidenceSource.documentRequest:id,sequence_number',
        'evidenceSource.acceptedVersion.uploader:id,name',
        'evidenceSource.reviewer:id,name',
    ];

    /** @use HasFactory<FileFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => Classification::class,
            'encryption_pending_since' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return BelongsTo<FileVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FileVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<FileVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class);
    }

    /**
     * Slot permintaan yang versi diterimanya disalin menjadi berkas ini di "04 Bukti Unit" (US-17).
     *
     * @return HasOne<RequestItem, $this>
     */
    public function evidenceSource(): HasOne
    {
        return $this->hasOne(RequestItem::class, 'evidence_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Isi berkas berlabel Rahasia, atau berkas mana pun di bawah folder Rahasia, disimpan terenkripsi (US-12).
     */
    public function requiresEncryption(): bool
    {
        return $this->classification === Classification::Rahasia || $this->folder->isWithinConfidential();
    }

    /**
     * Whether the version's stored content still has to be encrypted or decrypted to follow the label.
     */
    public function encryptionSyncStatusOf(FileVersion $version): ?EncryptionSyncStatus
    {
        $mustBeEncrypted = $this->requiresEncryption();

        if ($version->is_encrypted === $mustBeEncrypted) {
            return null;
        }

        return $mustBeEncrypted ? EncryptionSyncStatus::MenungguEnkripsi : EncryptionSyncStatus::MenungguDekripsi;
    }

    /**
     * The pending change for the file as a whole, when any of its versions does not follow the label yet.
     * Needs the versions relation, which may be loaded with only id, file_id, and is_encrypted.
     */
    public function encryptionSyncStatus(): ?EncryptionSyncStatus
    {
        foreach ($this->versions as $version) {
            if (($status = $this->encryptionSyncStatusOf($version)) !== null) {
                return $status;
            }
        }

        return null;
    }

    /**
     * Limit the query to files, including trashed ones, with a version whose stored content does not
     * follow the label yet; the SQL counterpart of encryptionSyncStatus().
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithEncryptionMismatch(Builder $query): void
    {
        $confidentialFolders = 'files.folder_id in '.Folder::confidentialFolderIdsSql();

        $query->where(fn (Builder $mismatch) => $mismatch
            ->where(fn (Builder $mustBeEncrypted) => $mustBeEncrypted
                ->where(fn (Builder $confidential) => $confidential
                    ->where('files.classification', Classification::Rahasia->value)
                    ->orWhereRaw($confidentialFolders))
                ->whereHas('versions', fn (Builder $versions) => $versions->where('is_encrypted', false)))
            ->orWhere(fn (Builder $mustBePlain) => $mustBePlain
                ->where('files.classification', '!=', Classification::Rahasia->value)
                ->whereRaw('not ('.$confidentialFolders.')')
                ->whereHas('versions', fn (Builder $versions) => $versions->where('is_encrypted', true))));
    }

    /**
     * Limit the query to files the user may view, with the same rules as FilePolicy::view, applied in SQL
     * so that search and lists never load files the user cannot see (US-14).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole(RoleSlug::DewanPengawas)) {
            return;
        }

        if (! $user->hasRole(RoleSlug::AuditorSpi)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $memberAssignments = fn (bool $confidentialOnly) => fn (QueryBuilder $members) => $members
            ->select('assignment_id')
            ->from('assignment_members')
            ->where('user_id', $user->id)
            ->when($confidentialOnly, fn (QueryBuilder $members) => $members->where('can_access_confidential', true));

        $isNotConfidential = fn (Builder $files) => $files
            ->where('files.classification', '!=', Classification::Rahasia->value)
            ->whereRaw('files.folder_id not in '.Folder::confidentialFolderIdsSql());

        $query->where(fn (Builder $visible) => $visible
            // Penugasan tempat ia ditunjuk: semua isinya jika diberi akses Rahasia, selain itu tanpa yang Rahasia.
            ->where(fn (Builder $assignmentFiles) => $assignmentFiles
                ->whereHas('folder', fn (Builder $folders) => $folders->whereIn('assignment_id', $memberAssignments(false)))
                ->where(fn (Builder $allowed) => $allowed
                    ->whereHas('folder', fn (Builder $folders) => $folders->whereIn('assignment_id', $memberAssignments(true)))
                    ->orWhere($isNotConfidential)))
            // Folder umum: semua kecuali yang Rahasia.
            ->orWhere(fn (Builder $generalFiles) => $generalFiles
                ->whereHas('folder', fn (Builder $folders) => $folders->whereNull('assignment_id'))
                ->where($isNotConfidential)));
    }

    /**
     * Nama dari browser hanya dipakai sebagai label: buang path dan karakter kontrol agar aman
     * dipakai di header Content-Disposition dan tampilan.
     */
    public static function normalizeName(string $clientName): string
    {
        $name = Str::afterLast(str_replace('\\', '/', $clientName), '/');

        return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '');
    }
}
