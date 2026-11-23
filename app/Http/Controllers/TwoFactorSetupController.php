<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aktivasi 2FA aplikasi (H-02, US-02).
 */
class TwoFactorSetupController extends Controller
{
    public function show(Request $request, TwoFactorAuthenticator $twoFactor): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->route('dasbor');
        }

        if ($user->two_factor_secret === null) {
            $user->forceFill(['two_factor_secret' => $twoFactor->generateSecret()])->save();
        }

        return Inertia::render('Keamanan/AktivasiDuaFaktor', [
            'qrCode' => $twoFactor->qrCodeDataUri($user, $user->two_factor_secret),
            'secret' => $user->two_factor_secret,
        ]);
    }

    public function store(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->route('dasbor');
        }

        $validated = $request->validate(
            ['code' => ['required', 'digits:6']],
            ['code.required' => 'Masukkan kode 6 digit dari aplikasi autentikator.', 'code.digits' => 'Kode harus 6 digit angka.'],
        );

        if (! $twoFactor->verifyCode($user, $validated['code'])) {
            return back()->withErrors(['code' => 'Kode tidak valid. Pastikan jam di perangkat Anda tepat, lalu coba lagi.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $recoveryCodes = $twoFactor->regenerateRecoveryCodes($user);

        $request->session()->put(EnsureTwoFactorIsVerified::SessionKey, true);

        Inertia::flash('recoveryCodes', $recoveryCodes);

        return redirect()->route('dua-faktor.kode-cadangan');
    }
}
