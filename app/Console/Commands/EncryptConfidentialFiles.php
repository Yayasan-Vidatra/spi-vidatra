<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\Encryption\ConfidentialFileEncryption;
use App\Services\Encryption\FileCipher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Menjadwalkan enkripsi ulang semua berkas yang enkripsinya belum sesuai label, misalnya berkas Rahasia
 * yang diunggah sebelum US-12 (US-12). Berkas yang sudah sesuai tidak dijadwalkan.
 */
#[Signature('berkas:enkripsi-rahasia')]
#[Description('Menyamakan enkripsi semua berkas dengan labelnya (berkas lama sebelum US-12)')]
class EncryptConfidentialFiles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FileCipher $cipher, ConfidentialFileEncryption $encryption): int
    {
        if (! $cipher->isAvailable()) {
            $this->error('Kunci enkripsi berkas Rahasia belum terpasang. Jalankan php artisan berkas:buat-kunci lebih dulu.');

            return self::FAILURE;
        }

        $count = 0;

        File::withTrashed()->withEncryptionMismatch()->lazyById()->each(function (File $file) use ($encryption, &$count): void {
            $encryption->syncFile($file);
            $count++;
        });

        $this->info("{$count} berkas dijadwalkan untuk enkripsi ulang.");

        return self::SUCCESS;
    }
}
