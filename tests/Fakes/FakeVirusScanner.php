<?php

namespace Tests\Fakes;

use App\Services\Scanning\ScanFailedException;
use App\Services\Scanning\ScanResult;
use App\Services\Scanning\VirusScanner;

/**
 * Pemindai palsu untuk test: memberi putusan yang ditentukan dan mencatat isi yang dipindai.
 */
class FakeVirusScanner implements VirusScanner
{
    /**
     * @var list<string>
     */
    public array $scannedContents = [];

    private function __construct(private ?ScanResult $result) {}

    public static function clean(): self
    {
        return new self(ScanResult::clean());
    }

    public static function infected(string $signature = 'Eicar-Test-Signature'): self
    {
        return new self(ScanResult::infected($signature));
    }

    public static function failing(): self
    {
        return new self(null);
    }

    /**
     * @param  resource  $stream
     */
    public function scan($stream): ScanResult
    {
        $this->scannedContents[] = stream_get_contents($stream);

        return $this->result ?? throw new ScanFailedException('clamd: INSTREAM size limit exceeded. ERROR');
    }
}
