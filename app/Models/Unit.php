<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit yayasan (SD YPVDP, SMP YPVDP, SMA YPVDP, Pelaksana Harian, Yayasan). Diisi UnitSeeder.
 */
#[Fillable(['code', 'name'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * PIC Unit untuk unit ini; satu unit boleh punya lebih dari satu PIC.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * PIC Unit aktif yang menerima permintaan dokumen untuk unit ini (US-15).
     *
     * @return HasMany<User, $this>
     */
    public function picUsers(): HasMany
    {
        return $this->users()
            ->where('is_active', true)
            ->whereRelation('role', 'slug', RoleSlug::PicUnit);
    }

    /**
     * @return HasMany<DocumentRequest, $this>
     */
    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }
}
