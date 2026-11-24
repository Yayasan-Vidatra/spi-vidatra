<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Penyimpanan berkas (Rancangan 6.3)
    |--------------------------------------------------------------------------
    |
    | Disk privat di luar web root. Versi berkas disimpan di "versi/<uuid>",
    | versi terinfeksi dipindah ke "karantina/<uuid>".
    |
    */

    'disk' => env('BERKAS_DISK', 'berkas'),

    /*
    |--------------------------------------------------------------------------
    | Batas unggahan (US-09, Rancangan 6.4)
    |--------------------------------------------------------------------------
    |
    | Batas ukuran dan jenis berkas sementara diatur di sini, lalu dipindah ke
    | Pengaturan (US-29). Jenis berkas harus bagian dari yang dikenali
    | App\Services\FileTypeDetector.
    |
    */

    'max_size_mb' => (int) env('BERKAS_MAX_SIZE_MB', 50),

    'max_files_per_upload' => 20,

    'allowed_extensions' => ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png', 'zip'],

    'preview_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],

    /*
    |--------------------------------------------------------------------------
    | Tautan unduh (US-11, Rancangan 6.2)
    |--------------------------------------------------------------------------
    */

    'signed_url_ttl_minutes' => 5,

    /*
    |--------------------------------------------------------------------------
    | Pemindai antivirus (US-09)
    |--------------------------------------------------------------------------
    |
    | "clamav" memindai lewat clamd (protokol INSTREAM). "lokal" menandai semua
    | berkas bersih dan hanya boleh dipakai di APP_ENV local atau testing.
    |
    */

    'scanner' => env('BERKAS_SCANNER', 'clamav'),

    'clamav' => [
        // tcp://host:port atau unix:///path/ke/clamd.ctl
        'socket' => env('CLAMAV_SOCKET', 'tcp://127.0.0.1:3310'),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
    ],

];
