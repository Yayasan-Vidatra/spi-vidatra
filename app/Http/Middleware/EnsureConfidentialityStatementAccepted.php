<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan semua halaman sampai pengguna menyetujui pernyataan kerahasiaan (US-03).
 */
class EnsureConfidentialityStatementAccepted
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->hasAcceptedConfidentialityStatement()) {
            return redirect()->guest(route('kerahasiaan.show'));
        }

        return $next($request);
    }
}
