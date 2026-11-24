<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Membuat kunci enkripsi berkas Rahasia (US-12) di path BERKAS_KEY_PATH. Tidak pernah menimpa kunci yang
 * ada, karena berkas yang sudah dienkripsi tidak bisa dibuka lagi tanpa kunci lamanya.
 */
#[Signature('berkas:buat-kunci')]
#[Description('Membuat kunci enkripsi berkas Rahasia (sekali saat pemasangan)')]
class CreateFileEncryptionKey extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = config('berkas.encryption.key_path');

        if (file_exists($path)) {
            $this->error("Kunci sudah ada di {$path}. Kunci tidak ditimpa karena berkas Rahasia yang ada tidak akan bisa dibuka lagi.");

            return self::FAILURE;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            $this->error("Folder {$directory} tidak bisa dibuat.");

            return self::FAILURE;
        }

        if (file_put_contents($path, base64_encode(random_bytes(32)).PHP_EOL, LOCK_EX) === false) {
            $this->error("Kunci tidak bisa ditulis ke {$path}.");

            return self::FAILURE;
        }

        chmod($path, 0400);

        $this->info("Kunci enkripsi berkas Rahasia dibuat di {$path} (hak 0400).");
        $this->warn('Simpan salinannya di tempat aman yang dipegang Dewan Pengawas, terpisah dari cadangan server.');

        return self::SUCCESS;
    }
}
