<?php

namespace App\Services\Scanning;

/**
 * Pemindai antivirus berkas unggahan (US-09, Rancangan 6.4).
 */
interface VirusScanner
{
    /**
     * Scan the content of a readable stream.
     *
     * @param  resource  $stream
     *
     * @throws ScanFailedException when the scanner cannot give a verdict; the version then stays pending.
     */
    public function scan($stream): ScanResult;
}
