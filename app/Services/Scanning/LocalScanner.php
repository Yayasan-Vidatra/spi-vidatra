<?php

namespace App\Services\Scanning;

use LogicException;

/**
 * Pemindai untuk pengembangan tanpa clamd: semua berkas dinyatakan bersih. Menolak dibuat di luar
 * APP_ENV local dan testing agar server tidak pernah meloloskan berkas tanpa pemindaian sungguhan.
 */
class LocalScanner implements VirusScanner
{
    private const AllowedEnvironments = ['local', 'testing'];

    public function __construct(string $environment)
    {
        if (! in_array($environment, self::AllowedEnvironments, true)) {
            throw new LogicException("Pemindai lokal tidak boleh dipakai di APP_ENV {$environment}. Atur BERKAS_SCANNER=clamav.");
        }
    }

    /**
     * @param  resource  $stream
     */
    public function scan($stream): ScanResult
    {
        return ScanResult::clean();
    }
}
