<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengeluarkan pengguna yang akunnya dinonaktifkan atau perannya dicabut
 * setelah masuk, sehingga akses tetap default ditolak.
 */
class EnsureAccountHasAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->canSignIn()) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (! $user->is_active) {
            Inertia::flash('error', 'Akun Anda tidak aktif. Hubungi Admin IT Yayasan.');

            return redirect()->route('login');
        }

        Inertia::flash('email', $user->email);

        return redirect()->route('akses.belum-diberi');
    }
}
