<?php

namespace App\Services\Encryption;

use RuntimeException;

/**
 * Isi terenkripsi tidak bisa dibuka: format tidak dikenali, kunci berbeda, atau isinya rusak atau diubah.
 */
class DecryptionFailedException extends RuntimeException {}
