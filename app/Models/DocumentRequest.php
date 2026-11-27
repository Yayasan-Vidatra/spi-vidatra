<?php

namespace App\Models;

use App\Enums\DocumentRequestStatus;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\DocumentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Permintaan dokumen dari SPI ke satu unit (US-15). Nomor urut, subfolder, status, dan waktu pengingat
 * diisi oleh aplikasi, bukan dari input pengguna.
 */
#[Fillable(['unit_id', 'due_date'])]
class DocumentRequest extends Model
{
    /**
     * Slot yang belum Diterima sekian hari kerja setelah tenggat diteruskan ke Dewan Pengawas (Piagam Pasal 17 ayat (2)).
     */
    public const int CharterEscalationWorkingDays = 10;

    /** @use HasFactory<DocumentRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentRequestStatus::class,
            'sequence_number' => 'integer',
            'due_date' => 'date',
            'due_soon_reminded_at' => 'datetime',
            'due_today_reminded_at' => 'datetime',
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
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Subfolder permintaan di bawah "02 Permintaan Dokumen" yang menampung berkas slot.
     *
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<RequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class)->orderBy('sort_order');
    }

    /**
     * The moment the deadline passes: the end of the due date in the foundation's working timezone.
     */
    public function dueAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->due_date->toDateString(), config('app.business_timezone'))->endOfDay();
    }

    /**
     * The last day of the Piagam period: the tenth working day after the due date. Slots not yet Diterima once
     * this day has ended are escalated to Dewan Pengawas (F-21).
     */
    public function charterDeadline(WorkingCalendar $calendar): CarbonImmutable
    {
        return $calendar->addWorkingDays($this->due_date, self::CharterEscalationWorkingDays);
    }

    public function isPastDue(?CarbonInterface $now = null): bool
    {
        return ($now ?? now())->greaterThan($this->dueAt());
    }
}
