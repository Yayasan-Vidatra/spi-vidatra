<?php

namespace Tests\Feature\Services\Scanning;

use App\Services\Scanning\ClamAvScanner;
use App\Services\Scanning\LocalScanner;
use App\Services\Scanning\VirusScanner;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LocalScannerTest extends TestCase
{
    #[TestWith(['production'])]
    #[TestWith(['staging'])]
    public function test_refuses_to_run_outside_local_and_testing(string $environment): void
    {
        $this->assertThrows(fn () => new LocalScanner($environment), LogicException::class);
    }

    public function test_container_refuses_the_local_scanner_in_production(): void
    {
        config(['berkas.scanner' => 'lokal']);
        $this->app['env'] = 'production';

        $this->assertThrows(fn () => $this->app->make(VirusScanner::class), LogicException::class);
    }

    public function test_container_uses_clamav_when_configured(): void
    {
        config(['berkas.scanner' => 'clamav']);

        $this->assertInstanceOf(ClamAvScanner::class, $this->app->make(VirusScanner::class));
    }

    public function test_marks_content_clean_in_testing(): void
    {
        $stream = fopen('php://memory', 'r');

        $this->assertFalse((new LocalScanner('testing'))->scan($stream)->isInfected);
    }
}
