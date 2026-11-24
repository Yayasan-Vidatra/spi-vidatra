<?php

namespace App\Services\Scanning;

/**
 * Hasil pemindaian yang pasti: bersih atau terinfeksi. Kegagalan pindai bukan hasil, melainkan ScanFailedException.
 */
final readonly class ScanResult
{
    private function __construct(
        public bool $isInfected,
        public ?string $signature,
    ) {}

    public static function clean(): self
    {
        return new self(false, null);
    }

    public static function infected(string $signature): self
    {
        return new self(true, $signature);
    }
}
