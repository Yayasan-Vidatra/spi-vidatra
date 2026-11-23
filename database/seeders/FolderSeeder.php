<?php

namespace Database\Seeders;

use App\Enums\Classification;
use App\Models\Folder;
use Illuminate\Database\Seeder;

/**
 * Folder tingkat atas di luar penugasan (Rancangan 9.1). Dikelola Dewan Pengawas, dilihat Auditor SPI.
 */
class FolderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Regulasi dan Pedoman', 'Templat'] as $name) {
            $alreadySeeded = Folder::query()
                ->whereNull('assignment_id')
                ->whereNull('parent_id')
                ->where('name', $name)
                ->exists();

            if ($alreadySeeded) {
                continue;
            }

            (new Folder)->forceFill([
                'assignment_id' => null,
                'parent_id' => null,
                'name' => $name,
                'classification' => Classification::Internal,
                'is_locked' => false,
            ])->save();
        }
    }
}
