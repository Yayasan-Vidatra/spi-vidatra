<?php

namespace App\Services\Encryption;

use Generator;
use RuntimeException;

/**
 * Enkripsi isi berkas Rahasia (US-12, Rancangan 6.3): AES-256-GCM per potongan 1 MiB, dengan kunci dari
 * berkas terpisah dari APP_KEY yang tidak ikut cadangan.
 *
 * Format: header (magic, versi format, sidik jari kunci) lalu rekaman per potongan berisi nonce acak
 * 12 byte, ciphertext, dan tag 16 byte. Header, nomor urut potongan, dan penanda potongan terakhir ikut
 * diautentikasi, sehingga potongan yang diubah, ditukar urutannya, atau dibuang dari akhir ketahuan.
 * Berkas sebesar apa pun diproses tanpa dimuat utuh ke memori.
 */
class FileCipher
{
    public const int ChunkSize = 1_048_576;

    public const int HeaderLength = 13;

    public const int RecordOverhead = 28;

    private const string Magic = 'SPIB';

    private const string FormatVersion = "\x01";

    private const string Cipher = 'aes-256-gcm';

    private const int NonceLength = 12;

    private const int TagLength = 16;

    private ?string $key = null;

    public function __construct(private string $keyPath) {}

    /**
     * Whether a valid key is installed. Rahasia content must not be written or read without one.
     */
    public function isAvailable(): bool
    {
        try {
            $this->key();

            return true;
        } catch (EncryptionKeyMissingException) {
            return false;
        }
    }

    /**
     * Encrypt plaintext pieces of any size into the stored format.
     *
     * @param  iterable<string>  $plaintext
     * @return Generator<int, string>
     */
    public function encrypt(iterable $plaintext): Generator
    {
        $header = self::Magic.self::FormatVersion.$this->fingerprint($this->key());

        yield $header;

        $buffer = '';
        $index = 0;

        foreach ($plaintext as $piece) {
            $buffer .= $piece;

            // Lebih besar (bukan sama dengan) agar potongan terakhir selalu ditandai final, termasuk berkas kosong.
            while (strlen($buffer) > self::ChunkSize) {
                yield $this->seal(substr($buffer, 0, self::ChunkSize), $index++, false, $header);
                $buffer = substr($buffer, self::ChunkSize);
            }
        }

        yield $this->seal($buffer, $index, true, $header);
    }

    /**
     * Decrypt the stored format back into plaintext pieces.
     *
     * @param  resource  $stream
     * @return Generator<int, string>
     *
     * @throws DecryptionFailedException
     */
    public function decrypt($stream): Generator
    {
        $header = $this->readExactly($stream, self::HeaderLength);

        if (strlen($header) !== self::HeaderLength || ! str_starts_with($header, self::Magic.self::FormatVersion)) {
            throw new DecryptionFailedException('Isi berkas bukan format enkripsi yang dikenali.');
        }

        if (! hash_equals($this->fingerprint($this->key()), substr($header, 5))) {
            throw new DecryptionFailedException('Berkas dienkripsi dengan kunci lain.');
        }

        $recordLength = self::ChunkSize + self::RecordOverhead;
        $current = $this->readExactly($stream, $recordLength);

        if ($current === '') {
            throw new DecryptionFailedException('Isi berkas terenkripsi terpotong.');
        }

        for ($index = 0; ; $index++) {
            $next = strlen($current) === $recordLength ? $this->readExactly($stream, $recordLength) : '';
            $isFinal = $next === '';

            yield $this->open($current, $index, $isFinal, $header);

            if ($isFinal) {
                return;
            }

            $current = $next;
        }
    }

    private function seal(string $plaintext, int $index, bool $isFinal, string $header): string
    {
        $nonce = random_bytes(self::NonceLength);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::Cipher, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, $this->associatedData($header, $index, $isFinal), self::TagLength);

        if ($ciphertext === false) {
            throw new RuntimeException('Enkripsi isi berkas gagal.');
        }

        return $nonce.$ciphertext.$tag;
    }

    private function open(string $record, int $index, bool $isFinal, string $header): string
    {
        if (strlen($record) < self::RecordOverhead) {
            throw new DecryptionFailedException('Isi berkas terenkripsi terpotong.');
        }

        $nonce = substr($record, 0, self::NonceLength);
        $tag = substr($record, -self::TagLength);
        $ciphertext = substr($record, self::NonceLength, -self::TagLength);

        $plaintext = openssl_decrypt($ciphertext, self::Cipher, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, $this->associatedData($header, $index, $isFinal));

        if ($plaintext === false) {
            throw new DecryptionFailedException('Isi berkas terenkripsi rusak atau telah diubah.');
        }

        return $plaintext;
    }

    private function associatedData(string $header, int $index, bool $isFinal): string
    {
        return $header.pack('J', $index).($isFinal ? "\x01" : "\x00");
    }

    /**
     * @param  resource  $stream
     */
    private function readExactly($stream, int $length): string
    {
        $data = '';

        while (strlen($data) < $length && ! feof($stream)) {
            $part = fread($stream, $length - strlen($data));

            if ($part === false || $part === '') {
                break;
            }

            $data .= $part;
        }

        return $data;
    }

    private function fingerprint(string $key): string
    {
        return substr(hash('sha256', 'spi-vidatra-berkas:'.$key, true), 0, 8);
    }

    private function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $contents = is_file($this->keyPath) && is_readable($this->keyPath) ? file_get_contents($this->keyPath) : false;
        $key = $contents === false ? false : base64_decode(trim($contents), true);

        if ($key === false || strlen($key) !== 32) {
            throw new EncryptionKeyMissingException('Kunci enkripsi berkas Rahasia belum terpasang atau tidak valid.');
        }

        return $this->key = $key;
    }
}
