<?php

namespace Tests\Feature\Services\Scanning;

use App\Services\Scanning\ClamAvScanner;
use App\Services\Scanning\ScanFailedException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ClamAvScannerTest extends TestCase
{
    private ?Process $fakeClamd = null;

    protected function tearDown(): void
    {
        $this->fakeClamd?->stop(0);

        parent::tearDown();
    }

    /**
     * Start a fake clamd that answers one INSTREAM request with the given reply, and return its socket address.
     */
    private function fakeClamdReplying(string $reply): string
    {
        $this->fakeClamd = new Process([PHP_BINARY, base_path('tests/Fixtures/fake-clamd.php'), $reply]);
        $this->fakeClamd->start();
        $this->fakeClamd->waitUntil(fn (string $type, string $output): bool => str_contains($output, "\n"));

        return 'tcp://'.trim($this->fakeClamd->getOutput());
    }

    /**
     * @return resource
     */
    private function streamOf(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    public function test_ok_reply_is_a_clean_result(): void
    {
        $scanner = new ClamAvScanner($this->fakeClamdReplying('stream: OK'), 5);

        $result = $scanner->scan($this->streamOf(str_repeat('a', 20_000)));

        $this->assertFalse($result->isInfected);
    }

    public function test_found_reply_is_an_infected_result_with_the_signature(): void
    {
        $scanner = new ClamAvScanner($this->fakeClamdReplying('stream: Eicar-Signature FOUND'), 5);

        $result = $scanner->scan($this->streamOf('X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR'));

        $this->assertTrue($result->isInfected);
        $this->assertSame('Eicar-Signature', $result->signature);
    }

    public function test_size_limit_reply_is_an_error_and_never_clean(): void
    {
        $scanner = new ClamAvScanner($this->fakeClamdReplying('INSTREAM size limit exceeded. ERROR'), 5);

        $this->assertThrows(
            fn () => $scanner->scan($this->streamOf(str_repeat('a', 20_000))),
            ScanFailedException::class,
            'clamd: INSTREAM size limit exceeded. ERROR',
        );
    }

    public function test_unknown_reply_is_an_error(): void
    {
        $scanner = new ClamAvScanner($this->fakeClamdReplying('UNKNOWN COMMAND'), 5);

        $this->assertThrows(fn () => $scanner->scan($this->streamOf('isi')), ScanFailedException::class);
    }

    public function test_unreachable_clamd_is_an_error(): void
    {
        $scanner = new ClamAvScanner('tcp://127.0.0.1:1', 1);

        $this->assertThrows(fn () => $scanner->scan($this->streamOf('isi')), ScanFailedException::class);
    }
}
