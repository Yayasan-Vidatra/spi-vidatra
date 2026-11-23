<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Unit yayasan (Rancangan 9.2). Kode singkat dipakai di kode penugasan.
 */
class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            'SD' => 'SD YPVDP',
            'SMP' => 'SMP YPVDP',
            'SMA' => 'SMA YPVDP',
            'PH' => 'Pelaksana Harian',
            'YYS' => 'Yayasan',
        ];

        foreach ($units as $code => $name) {
            Unit::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
