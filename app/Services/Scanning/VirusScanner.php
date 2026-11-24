<?php

namespace App\Services\Scanning;

/**
 * Pemindai antivirus berkas unggahan (US-09, Rancangan 6.4).
 */
interface VirusScanner
{
    /**
     * Scan the original content, given in pieces so that encrypted content can be decrypted while it streams.
     *
     * @param  iterable<string>  $content
     *
     * @throws ScanFailedException when the scanner cannot give a verdict; the version then stays pending.
     */
    public function scan(iterable $content): ScanResult;
}
