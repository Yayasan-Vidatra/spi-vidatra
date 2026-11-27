<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\User;

/**
 * Hak melihat eskalasi tenggat Piagam (UC-17): Dewan Pengawas sebagai penerimanya.
 */
class EscalationPolicy
{
    /**
     * TODO(Langkah 2b): Kepala SPI, yang diberi tahu setiap eskalasi, juga melihat daftar ini.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleSlug::DewanPengawas);
    }
}
