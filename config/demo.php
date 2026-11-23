<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun Demo (non-produksi)
    |--------------------------------------------------------------------------
    |
    | Daftar "email:peran" dipisah koma untuk DemoAccountSeeder, misalnya
    | "auditor@vidatra.sch.id:auditor-spi,dp@vidatra.sch.id:dewan-pengawas".
    | Jalur sementara sampai alur persetujuan peran (US-22) selesai; seeder
    | hanya berjalan di APP_ENV local atau staging.
    |
    */

    'accounts' => env('DEMO_ACCOUNTS', ''),

    'allowed_environments' => ['local', 'staging'],

];
