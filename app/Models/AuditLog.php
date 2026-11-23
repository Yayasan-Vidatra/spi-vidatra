<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Baris log audit yang hanya bisa ditambah (US-24). Ditulis lewat AuditLogger.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Log audit tidak bisa diubah.'));
        static::deleting(fn () => throw new LogicException('Log audit tidak bisa dihapus.'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'action' => AuditAction::class,
            'properties' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Hash this row from its stored values and the previous row's hash.
     *
     * Kunci JSON diurutkan agar hash yang dihitung ulang dari baris di MySQL tetap sama,
     * karena MySQL menata ulang urutan kunci di kolom JSON.
     */
    public function calculateHash(): string
    {
        $payload = [
            'previous_hash' => $this->previous_hash,
            'occurred_at' => $this->occurred_at?->format('Y-m-d H:i:s'),
            'user_id' => $this->user_id,
            'action' => $this->action?->value,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'properties' => $this->properties === null ? null : self::sortKeysRecursively($this->properties),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function sortKeysRecursively(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values);
        }

        return array_map(
            fn (mixed $value): mixed => is_array($value) ? self::sortKeysRecursively($value) : $value,
            $values,
        );
    }
}
