<?php

namespace App\Services\Scanning;

/**
 * Memindai lewat clamd dengan protokol INSTREAM: perintah "zINSTREAM\0", lalu potongan data yang masing-masing
 * diawali panjang 4 byte big-endian, ditutup potongan berpanjang nol. clamd membalas "stream: OK",
 * "stream: <nama virus> FOUND", atau pesan "... ERROR".
 */
class ClamAvScanner implements VirusScanner
{
    private const ChunkSize = 8192;

    /**
     * @param  string  $socket  tcp://host:port atau unix:///path/ke/clamd.ctl
     */
    public function __construct(
        private string $socket,
        private int $timeoutSeconds,
    ) {}

    /**
     * @param  iterable<string>  $content
     */
    public function scan(iterable $content): ScanResult
    {
        $connection = @stream_socket_client($this->socket, $errorCode, $errorMessage, $this->timeoutSeconds);

        if ($connection === false) {
            throw new ScanFailedException("Tidak bisa terhubung ke clamd di {$this->socket}: {$errorMessage}");
        }

        try {
            stream_set_timeout($connection, $this->timeoutSeconds);

            $this->send($connection, $content);

            return $this->parseReply($this->readReply($connection));
        } finally {
            fclose($connection);
        }
    }

    /**
     * clamd may close the connection early, for example when StreamMaxLength is exceeded.
     * Sending then stops and its reply is still read.
     *
     * @param  resource  $connection
     * @param  iterable<string>  $content
     */
    private function send($connection, iterable $content): void
    {
        if (! $this->write($connection, "zINSTREAM\0")) {
            return;
        }

        foreach ($content as $piece) {
            foreach (str_split($piece, self::ChunkSize) as $chunk) {
                if ($chunk !== '' && ! $this->write($connection, pack('N', strlen($chunk)).$chunk)) {
                    return;
                }
            }
        }

        $this->write($connection, pack('N', 0));
    }

    /**
     * @param  resource  $connection
     */
    private function write($connection, string $data): bool
    {
        while ($data !== '') {
            $written = @fwrite($connection, $data);

            if ($written === false || $written === 0) {
                return false;
            }

            $data = substr($data, $written);
        }

        return true;
    }

    /**
     * @param  resource  $connection
     */
    private function readReply($connection): string
    {
        $reply = '';

        while (! feof($connection) && ! str_contains($reply, "\0")) {
            $part = @fread($connection, 1024);

            if (stream_get_meta_data($connection)['timed_out']) {
                throw new ScanFailedException('clamd tidak membalas dalam batas waktu.');
            }

            if ($part === false) {
                break;
            }

            $reply .= $part;
        }

        return trim(strtok($reply, "\0") ?: '');
    }

    private function parseReply(string $reply): ScanResult
    {
        if ($reply === 'stream: OK') {
            return ScanResult::clean();
        }

        if (preg_match('/^stream: (.+) FOUND$/', $reply, $matches) === 1) {
            return ScanResult::infected($matches[1]);
        }

        throw new ScanFailedException($reply === '' ? 'clamd menutup koneksi tanpa balasan.' : "clamd: {$reply}");
    }
}
