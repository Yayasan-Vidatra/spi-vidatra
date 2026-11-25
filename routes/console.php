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
