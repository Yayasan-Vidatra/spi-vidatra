<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Berkas yang sudah 30 hari di Tempat Sampah dihapus permanen (US-13).
Schedule::command('berkas:kosongkan-sampah')->dailyAt('02:00')->withoutOverlapping();

// Enkripsi ulang yang tertahan lebih dari 10 menit dijadwalkan ulang (US-12).
Schedule::command('berkas:periksa-enkripsi')->everyFiveMinutes()->withoutOverlapping();

// Salinan slot Diterima ke 04 Bukti Unit yang tertahan lebih dari 60 menit dijadwalkan ulang (US-17).
Schedule::command('permintaan:periksa-salinan')->everyFifteenMinutes()->withoutOverlapping();

// Pengingat tenggat H-3 (3 hari kerja) dan hari-H ke PIC unit, setiap pagi waktu yayasan (US-18).
Schedule::command('permintaan:kirim-pengingat')->dailyAt('07:00')->timezone(config('app.business_timezone'))->withoutOverlapping();

// Slot yang belum Diterima setelah 10 hari kerja sesudah tenggat diteruskan ke Dewan Pengawas (US-18, F-21).
// Perintahnya sendiri juga berhenti di hari libur nasional dan cuti bersama.
Schedule::command('permintaan:eskalasi')->weekdays()->dailyAt('07:00')->timezone(config('app.business_timezone'))->withoutOverlapping();
