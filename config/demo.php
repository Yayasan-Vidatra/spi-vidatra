<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun Demo (non-produksi)
    |--------------------------------------------------------------------------
    |
    | Daftar "email:peran" dipisah koma untuk DemoAccountSeeder, misalnya
    | "auditor@vidatra.sch.id:auditor-spi,dp@vidatra.sch.id:dewan-pengawas".
    | PIC Unit wajib memakai "email:pic-unit:kode-unit" dengan kode dari
    | tabel units (SD, SMP, SMA, PH, YYS), misalnya "tu.smp@vidatra.sch.id:pic-unit:SMP".
    | Jalur sementara sampai alur persetujuan peran (US-22) selesai; seeder
    | hanya berjalan di APP_ENV local atau staging.
    |
    */

    'accounts' => env('DEMO_ACCOUNTS', ''),

    'allowed_environments' => ['local', 'staging'],

];
