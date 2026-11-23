<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Satu-satunya tempat data peran diisi, termasuk kewajiban 2FA (US-02).
 * Tidak ada form atau resource Filament yang mengubah requires_two_factor.
 */
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [RoleSlug::DewanPengawas, 'Dewan Pengawas', true],
            [RoleSlug::AuditorSpi, 'Auditor SPI', true],
            [RoleSlug::PicUnit, 'PIC Unit', false],
            [RoleSlug::PengurusYayasan, 'Pengurus Yayasan', false],
            [RoleSlug::PeninjauTamu, 'Peninjau Tamu', false],
            [RoleSlug::SuperAdmin, 'Super Admin', true],
        ];

        foreach ($roles as [$slug, $name, $requiresTwoFactor]) {
            Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'requires_two_factor' => $requiresTwoFactor],
            );
        }
    }
}
