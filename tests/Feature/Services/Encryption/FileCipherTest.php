<?php

namespace Tests\Feature\Services\Encryption;

use App\Services\Encryption\DecryptionFailedException;
use App\Services\Encryption\EncryptionKeyMissingException;
use App\Services\Encryption\FileCipher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FileCipherTest extends TestCase
{
    private function cipher(?string $keyPath = null): FileCipher
    {
        return new FileCipher($keyPath ?? config('berkas.encryption.key_path'));
    }

    private function encrypt(string $plaintext, ?FileCipher $cipher = null): string
    {
        return implode('', iterator_to_array(($cipher ?? $this->cipher())->encrypt(str_split($plaintext, 300_000) ?: ['']), false));
    }

    private function decrypt(string $ciphertext, ?FileCipher $cipher = null): string
    {
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $ciphertext);
        rewind($stream);

        return implode('', iterator_to_array(($cipher ?? $this->cipher())->decrypt($stream), false));
    }

    private function otherKeyPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kunci-lain');
        file_put_contents($path, base64_encode(random_bytes(32)));

        return $path;
    }

    /**
     * @return array<string, array{int}>
     */
    public static function plaintextSizes(): array
    {
        return [
            'empty' => [0],
            'small' => [100],
            'exactly one chunk' => [FileCipher::ChunkSize],
            'one byte over a chunk' => [FileCipher::ChunkSize + 1],
            'several chunks' => [(int) (FileCipher::ChunkSize * 2.5)],
        ];
    }

    #[DataProvider('plaintextSizes')]
    public function test_round_trips_content_of_any_size(int $size): void
    {
        $plaintext = $size === 0 ? '' : random_bytes($size);

        $this->assertSame($plaintext, $this->decrypt($this->encrypt($plaintext)));
    }

    public function test_ciphertext_does_not_contain_the_plaintext(): void
    {
        $plaintext = str_repeat('Temuan audit kas sekolah. ', 1000);

        $ciphertext = $this->encrypt($plaintext);

        $this->assertStringNotContainsString('Temuan audit', $ciphertext);
        $this->assertStringStartsWith('SPIB', $ciphertext);
    }

    public function test_changed_byte_is_detected(): void
    {
        $ciphertext = $this->encrypt('isi rahasia');
        $position = FileCipher::HeaderLength + 15;
        $ciphertext[$position] = $ciphertext[$position] === 'a' ? 'b' : 'a';

        $this->assertThrows(fn () => $this->decrypt($ciphertext), DecryptionFailedException::class);
    }

    public function test_removed_final_chunk_is_detected(): void
    {
        $ciphertext = $this->encrypt(random_bytes(FileCipher::ChunkSize * 2));
        $truncated = substr($ciphertext, 0, FileCipher::HeaderLength + FileCipher::ChunkSize + FileCipher::RecordOverhead);

        $this->assertThrows(fn () => $this->decrypt($truncated), DecryptionFailedException::class);
    }

    public function test_reordered_chunks_are_detected(): void
    {
        $ciphertext = $this->encrypt(random_bytes(FileCipher::ChunkSize * 2 + 10));
        $recordLength = FileCipher::ChunkSize + FileCipher::RecordOverhead;
        $header = substr($ciphertext, 0, FileCipher::HeaderLength);
        $first = substr($ciphertext, FileCipher::HeaderLength, $recordLength);
        $second = substr($ciphertext, FileCipher::HeaderLength + $recordLength, $recordLength);
        $rest = substr($ciphertext, FileCipher::HeaderLength + 2 * $recordLength);

        $this->assertThrows(fn () => $this->decrypt($header.$second.$first.$rest), DecryptionFailedException::class);
    }

    public function test_content_encrypted_with_another_key_is_refused(): void
    {
        $ciphertext = $this->encrypt('isi rahasia', $this->cipher($this->otherKeyPath()));

        $this->assertThrows(fn () => $this->decrypt($ciphertext), DecryptionFailedException::class, 'Berkas dienkripsi dengan kunci lain.');
    }

    public function test_plain_content_is_not_mistaken_for_ciphertext(): void
    {
        $this->assertThrows(fn () => $this->decrypt('%PDF-1.4 bukan terenkripsi'), DecryptionFailedException::class);
    }

    public function test_missing_or_invalid_key_is_unavailable_and_refuses_to_encrypt(): void
    {
        $invalidKeyPath = tempnam(sys_get_temp_dir(), 'kunci-rusak');
        file_put_contents($invalidKeyPath, 'bukan kunci');

        foreach ([sys_get_temp_dir().'/tidak-ada.key', $invalidKeyPath] as $path) {
            $cipher = $this->cipher($path);

            $this->assertFalse($cipher->isAvailable());
            $this->assertThrows(fn () => $this->encrypt('isi', $cipher), EncryptionKeyMissingException::class);
        }
    }
}
