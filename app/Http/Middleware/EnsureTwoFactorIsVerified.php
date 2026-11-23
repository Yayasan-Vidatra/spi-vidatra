<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan semua halaman sampai 2FA aplikasi aktif (untuk peran yang wajib)
 * dan kodenya sudah diverifikasi di sesi ini (US-02).
 */
class EnsureTwoFactorIsVerified
{
    public const string SessionKey = 'auth.two_factor_verified';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($request->session()->get(self::SessionKey) === true) {
            return $next($request);
        }

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->guest(route('dua-faktor.verifikasi'));
        }

        if ($user->requiresTwoFactor()) {
            return redirect()->guest(route('dua-faktor.aktivasi'));
        }

        return $next($request);
    }
}
