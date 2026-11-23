<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Menampilkan kode cadangan sekali, tepat setelah 2FA diaktifkan.
 * Kodenya hanya dikirim sebagai flash data, jadi tidak muncul lagi saat halaman dimuat ulang.
 */
class TwoFactorRecoveryCodeController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Keamanan/KodeCadangan');
    }
}
