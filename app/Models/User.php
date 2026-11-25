<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Pengguna yang masuk lewat Google. `role_id` sengaja tidak fillable:
 * perubahan peran melewati alur persetujuan (US-22). `unit_id` juga tidak fillable karena
 * menentukan data unit yang dilihat PIC Unit (US-16).
 */
#[Fillable(['name', 'email', 'google_id'])]
#[Hidden(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_used_timestep'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Akun tidak memakai remember-me agar batas sesi tidak aktif (US-04) tidak terlewati.
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_timestep' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Unit PIC Unit. Peran lain tidak punya unit.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<ConfidentialityAcceptance, $this>
     */
    public function confidentialityAcceptances(): HasMany
    {
        return $this->hasMany(ConfidentialityAcceptance::class);
    }

    public function hasRole(RoleSlug $roleSlug): bool
    {
        return $this->role?->slug === $roleSlug;
    }

    /**
     * PIC Unit tanpa unit ditolak, karena data yang boleh ia lihat dibatasi per unit (US-16).
     */
    public function canSignIn(): bool
    {
        if (! $this->is_active || $this->role_id === null) {
            return false;
        }

        return ! $this->hasRole(RoleSlug::PicUnit) || $this->unit_id !== null;
    }

    public function requiresTwoFactor(): bool
    {
        return (bool) $this->role?->requires_two_factor;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function hasAcceptedConfidentialityStatement(): bool
    {
        return $this->confidentialityAcceptances()->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole(RoleSlug::SuperAdmin);
    }
}
