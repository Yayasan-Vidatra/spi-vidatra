<?php

namespace App\Models;

use Database\Factories\AssignmentMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditor yang ditunjuk ke penugasan (US-06), termasuk tanda akses folder Rahasia (US-07).
 * Semua kolom diisi eksplisit oleh controller agar tidak bisa diatur lewat mass assignment.
 */
class AssignmentMember extends Model
{
    /** @use HasFactory<AssignmentMemberFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_access_confidential' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
