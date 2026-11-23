<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data baku yang dibutuhkan aplikasi dan test: peran, unit, templat folder, dan folder umum.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UnitSeeder::class,
            FolderTemplateSeeder::class,
            FolderSeeder::class,
        ]);
    }
}
