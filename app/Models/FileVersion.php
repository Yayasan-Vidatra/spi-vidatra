<?php

namespace App\Models;

use App\Enums\ScanStatus;
use Database\Factories\FileVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu versi isi berkas (US-10). Disimpan di disk privat dengan nama UUID; nama asli hanya di File.
 */
class FileVersion extends Model
{
    /** @use HasFactory<FileVersionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'size' => 'integer',
            'is_encrypted' => 'boolean',
            'scan_status' => ScanStatus::class,
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * Termasuk berkas di Tempat Sampah, karena versinya tetap ada sampai dihapus permanen.
     *
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function restoredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'restored_from_version_id');
    }

    public function hasPassedScan(): bool
    {
        return $this->scan_status === ScanStatus::Bersih;
    }

    /**
     * PDF dan gambar bisa dipratinjau di browser (US-11); jenis lain hanya bisa diunduh.
     */
    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, config('berkas.preview_mime_types'), true);
    }
}
