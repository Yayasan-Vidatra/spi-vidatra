<?php

namespace Database\Seeders;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * Libur nasional dan cuti bersama untuk hitungan hari kerja (F-21, US-18).
 *
 * - 2027: SKB Menteri Agama, Menteri Ketenagakerjaan, dan Menteri PANRB Nomor 1205, 3, 2 Tahun 2026, ditetapkan
 *   15 September 2026 (sumber: kemenkopmk.go.id).
 * - 2026: hanya sisa tahun (Desember); SKB Menteri Agama, Menteri Ketenagakerjaan, dan Menteri PANRB Nomor 1497, 2,
 *   5 Tahun 2025.
 *
 * Tanggal yang jatuh di akhir pekan tetap dicatat agar daftarnya utuh. Seeder hanya menambah atau memperbarui
 * per tanggal; tanggal lain di tabel tidak dihapus.
 */
class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->holidays() as [$date, $type, $description]) {
            Holiday::query()->updateOrCreate(['date' => $date], ['type' => $type, 'description' => $description]);
        }
    }

    /**
     * @return list<array{0: string, 1: HolidayType, 2: string}>
     */
    private function holidays(): array
    {
        $nasional = HolidayType::LiburNasional;
        $cuti = HolidayType::CutiBersama;

        return [
            // 2026, sisa tahun, SKB Nomor 1497, 2, 5 Tahun 2025.
            ['2026-12-24', $cuti, 'Cuti bersama Kelahiran Yesus Kristus'],
            ['2026-12-25', $nasional, 'Kelahiran Yesus Kristus'],

            // 2027, SKB Nomor 1205, 3, 2 Tahun 2026: libur nasional.
            ['2027-01-01', $nasional, 'Tahun Baru 2027 Masehi'],
            ['2027-01-05', $nasional, 'Isra Mikraj Nabi Muhammad SAW 1448 H'],
            ['2027-02-06', $nasional, 'Tahun Baru Imlek 2578 Kongzili'],
            ['2027-03-08', $nasional, 'Hari Suci Nyepi (Tahun Baru Saka 1949)'],
            ['2027-03-10', $nasional, 'Idulfitri 1448 H'],
            ['2027-03-11', $nasional, 'Idulfitri 1448 H'],
            ['2027-03-26', $nasional, 'Wafat Yesus Kristus'],
            ['2027-03-28', $nasional, 'Kebangkitan Yesus Kristus (Paskah)'],
            ['2027-05-01', $nasional, 'Hari Buruh Internasional'],
            ['2027-05-06', $nasional, 'Kenaikan Yesus Kristus'],
            ['2027-05-17', $nasional, 'Iduladha 1448 H'],
            ['2027-05-20', $nasional, 'Hari Raya Waisak 2571 BE'],
            ['2027-06-01', $nasional, 'Hari Lahir Pancasila'],
            ['2027-06-06', $nasional, 'Tahun Baru Islam 1449 H'],
            ['2027-08-15', $nasional, 'Maulid Nabi Muhammad SAW'],
            ['2027-08-17', $nasional, 'Proklamasi Kemerdekaan'],
            ['2027-12-25', $nasional, 'Kelahiran Yesus Kristus'],
            ['2027-12-26', $nasional, 'Isra Mikraj Nabi Muhammad SAW 1449 H'],

            // 2027, SKB Nomor 1205, 3, 2 Tahun 2026: cuti bersama.
            ['2027-02-05', $cuti, 'Cuti bersama Tahun Baru Imlek 2578 Kongzili'],
            ['2027-03-09', $cuti, 'Cuti bersama Idulfitri 1448 H'],
            ['2027-03-12', $cuti, 'Cuti bersama Idulfitri 1448 H'],
            ['2027-03-15', $cuti, 'Cuti bersama Idulfitri 1448 H'],
            ['2027-03-25', $cuti, 'Cuti bersama Wafat Yesus Kristus'],
            ['2027-05-18', $cuti, 'Cuti bersama Iduladha 1448 H'],
            ['2027-05-19', $cuti, 'Cuti bersama Hari Raya Waisak 2571 BE'],
            ['2027-12-24', $cuti, 'Cuti bersama Kelahiran Yesus Kristus'],
        ];
    }
}
