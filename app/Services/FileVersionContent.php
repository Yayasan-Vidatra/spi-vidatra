<?php

namespace App\Services;

use App\Models\FileVersion;
use App\Services\Encryption\FileCipher;
use Generator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Satu-satunya jalur baca dan tulis isi versi berkas di disk. Isi Rahasia dienkripsi saat ditulis dan
 * didekripsi sambil dialirkan saat dibaca, sehingga isi aslinya tidak pernah tersimpan tanpa enkripsi.
 */
class FileVersionContent
{
    private const int ReadSize = 1_048_576;

    public function __construct(private FileCipher $cipher) {}

    public function disk(): Filesystem
    {
        return Storage::disk(config('berkas.disk'));
    }

    /**
     * Stream the original content of a version, decrypting it when it is stored encrypted.
     *
     * @return Generator<int, string>
     */
    public function read(FileVersion $version): Generator
    {
        yield from $this->readPath($version->storage_path, $version->is_encrypted);
    }

    /**
     * @return Generator<int, string>
     */
    public function readPath(string $path, bool $isEncrypted): Generator
    {
        $stream = $this->disk()->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Isi berkas di {$path} tidak bisa dibaca.");
        }

        try {
            if ($isEncrypted) {
                yield from $this->cipher->decrypt($stream);

                return;
            }

            while (! feof($stream)) {
                $chunk = fread($stream, self::ReadSize);

                if ($chunk === false) {
                    throw new RuntimeException("Isi berkas di {$path} tidak bisa dibaca.");
                }

                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Write original content to the disk, encrypted when requested.
     *
     * @param  iterable<string>  $plaintext
     */
    public function write(string $path, iterable $plaintext, bool $encrypt): void
    {
        // php://temp hanya pernah berisi ciphertext untuk isi Rahasia, jadi aman jika tumpah ke disk sementara.
        $buffer = fopen('php://temp', 'w+b');

        try {
            foreach ($encrypt ? $this->cipher->encrypt($plaintext) : $plaintext as $piece) {
                fwrite($buffer, $piece);
            }

            rewind($buffer);
            $this->disk()->writeStream($path, $buffer);
        } finally {
            fclose($buffer);
        }
    }

    /**
     * Read a local file, such as an upload, in pieces.
     *
     * @return Generator<int, string>
     */
    public function readLocalFile(string $path): Generator
    {
        $stream = fopen($path, 'rb');

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, self::ReadSize);

                if ($chunk !== false && $chunk !== '') {
                    yield $chunk;
                }
            }
        } finally {
            fclose($stream);
        }
    }
}
