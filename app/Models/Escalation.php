<?php

namespace App\Models;

use App\Enums\EscalationStatus;
use App\Enums\EscalationType;
use Database\Factories\EscalationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eskalasi tenggat Piagam ke Dewan Pengawas (F-21, UC-17). Dibuat dan diselesaikan oleh aplikasi, bukan dari
 * input pengguna, sehingga tidak ada mass assignment.
 */
class Escalation extends Model
{
    /** @use HasFactory<EscalationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EscalationType::class,
            'status' => EscalationStatus::class,
            'charter_deadline' => 'date',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Objek yang dieskalasi, misalnya slot permintaan dokumen.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
