<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Pengguna tidak di-seed: akun dibuat saat login Google pertama, dan
     * peran pertama ditetapkan lewat `php artisan akun:tetapkan-peran`.
     */
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            ConfidentialityStatementSeeder::class,
        ]);
    }
}
