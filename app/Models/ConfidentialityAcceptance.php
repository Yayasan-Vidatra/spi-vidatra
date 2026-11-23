<?php

namespace App\Models;

use Database\Factories\ConfidentialityAcceptanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['confidentiality_statement_id', 'accepted_at', 'ip_address', 'user_agent'])]
class ConfidentialityAcceptance extends Model
{
    /** @use HasFactory<ConfidentialityAcceptanceFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
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
     * @return BelongsTo<ConfidentialityStatement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(ConfidentialityStatement::class, 'confidentiality_statement_id');
    }
}
