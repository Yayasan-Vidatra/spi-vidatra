<?php

namespace Tests;

use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Peran, unit, templat folder, dan folder umum adalah data baku, jadi diisi sekali
     * saat basis data uji dimigrasi.
     */
    protected bool $seed = true;

    protected string $seeder = ReferenceDataSeeder::class;

    private static ?string $encryptionKeyPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Kunci enkripsi berkas Rahasia khusus test, dibuat sekali per proses di folder sementara (US-12).
        config(['berkas.encryption.key_path' => self::encryptionKeyPath()]);
    }

    private static function encryptionKeyPath(): string
    {
        if (self::$encryptionKeyPath === null) {
            self::$encryptionKeyPath = tempnam(sys_get_temp_dir(), 'berkas-kunci');
            file_put_contents(self::$encryptionKeyPath, base64_encode(random_bytes(32)));
            register_shutdown_function(fn () => @unlink(self::$encryptionKeyPath));
        }

        return self::$encryptionKeyPath;
    }
}
