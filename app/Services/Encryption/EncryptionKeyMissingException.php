<?php

namespace App\Services\Encryption;

use RuntimeException;

/**
 * Kunci enkripsi berkas Rahasia tidak ada atau tidak valid. Aplikasi menolak menulis atau membaca isi
 * Rahasia, bukan menyimpannya tanpa enkripsi.
 */
class EncryptionKeyMissingException extends RuntimeException {}
