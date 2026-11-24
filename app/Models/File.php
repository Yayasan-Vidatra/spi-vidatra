<?php

namespace App\Models;

use App\Enums\Classification;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Berkas di sebuah folder (US-09). Isi, ukuran, hash, dan status pindai ada per versi di FileVersion.
 * Semua kolom diisi eksplisit oleh aplikasi; tidak ada mass assignment.
 */
class File extends Model
{
    /** @use HasFactory<FileFactory> */
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
