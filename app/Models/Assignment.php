<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penugasan audit (US-05). Kode, nomor urut, status, dan penutupan diisi
 * oleh aplikasi, bukan dari input pengguna.
 */
#[Fillable(['title', 'unit_id', 'start_date', 'end_date'])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'year' => 'integer',
            'sequence_number' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    /**
     * @return HasMany<AssignmentMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(AssignmentMember::class);
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function isClosed(): bool
    {
        return $this->status === AssignmentStatus::Ditutup;
    }

    public function memberFor(User $user): ?AssignmentMember
    {
        return $this->members->firstWhere('user_id', $user->id);
    }

    /**
     * Limit the query to assignments the user is a member of.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWhereMember(Builder $query, User $user): void
    {
        $query->whereHas('members', fn (Builder $members) => $members->where('user_id', $user->id));
    }
}
