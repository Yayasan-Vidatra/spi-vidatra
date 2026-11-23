<?php

namespace App\Models;

use Database\Factories\ConfidentialityStatementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Teks pernyataan kerahasiaan. Baris terbaru adalah teks yang berlaku.
 */
#[Fillable(['body'])]
class ConfidentialityStatement extends Model
{
    /** @use HasFactory<ConfidentialityStatementFactory> */
    use HasFactory;

    /**
     * Get the statement text currently in force.
     */
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /**
     * @return HasMany<ConfidentialityAcceptance, $this>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(ConfidentialityAcceptance::class);
    }
}
