<?php

namespace App\Services\Scanning;

use RuntimeException;

/**
 * Pemindai tidak bisa memberi putusan (clamd tidak terjangkau, batas ukuran clamd terlampaui, balasan tidak dikenal).
 * Berkas tidak pernah dianggap bersih karena kegagalan ini.
 */
class ScanFailedException extends RuntimeException {}
