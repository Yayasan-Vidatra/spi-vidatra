<?php

namespace Tests\Feature\Console\Commands;

use App\Services\Encryption\FileCipher;
use Tests\TestCase;

class CreateFileEncryptionKeyTest extends TestCase
{
    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyPath = sys_get_temp_dir().'/spi-kunci-'.uniqid().'/berkas-rahasia.key';
        config(['berkas.encryption.key_path' => $this->keyPath]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->keyPath)) {
            chmod($this->keyPath, 0600);
            unlink($this->keyPath);
            rmdir(dirname($this->keyPath));
        }

        parent::tearDown();
    }

    public function test_creates_a_usable_key_at_the_configured_path(): void
    {
        $this->artisan('berkas:buat-kunci')->assertSuccessful();

        $this->assertFileExists($this->keyPath);
        $this->assertSame(32, strlen(base64_decode(trim(file_get_contents($this->keyPath)), true)));
        $this->assertTrue((new FileCipher($this->keyPath))->isAvailable());
    }

    public function test_never_overwrites_an_existing_key(): void
    {
        $this->artisan('berkas:buat-kunci')->assertSuccessful();
        $originalKey = file_get_contents($this->keyPath);

        $this->artisan('berkas:buat-kunci')->assertFailed();

        $this->assertSame($originalKey, file_get_contents($this->keyPath));
    }
}
