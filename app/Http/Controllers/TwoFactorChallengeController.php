<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Verifikasi kode 2FA di setiap login (US-02).
 */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($this->isSatisfied($request)) {
            return redirect()->route('dasbor');
        }

        return Inertia::render('Keamanan/VerifikasiDuaFaktor');
    }

    public function store(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        if ($this->isSatisfied($request)) {
            return redirect()->route('dasbor');
        }

        $validated = $request->validate(
            [
                'code' => ['nullable', 'required_without:recovery_code', 'digits:6'],
                'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:20'],
            ],
            [
                'code.required_without' => 'Masukkan kode 6 digit atau kode cadangan.',
                'code.digits' => 'Kode harus 6 digit angka.',
                'recovery_code.required_without' => 'Masukkan kode 6 digit atau kode cadangan.',
            ],
        );

        $user = $request->user();
        $isUsingRecoveryCode = blank($validated['code'] ?? null);

        if (! $isUsingRecoveryCode && ! $twoFactor->verifyCode($user, $validated['code'])) {
            return back()->withErrors(['code' => 'Kode tidak valid.']);
        }

        if ($isUsingRecoveryCode && ! $twoFactor->useRecoveryCode($user, $validated['recovery_code'])) {
            return back()->withErrors(['recovery_code' => 'Kode cadangan tidak valid atau sudah dipakai.']);
        }

        $request->session()->regenerate();
        $request->session()->put(EnsureTwoFactorIsVerified::SessionKey, true);

        if ($isUsingRecoveryCode) {
            Inertia::flash('warning', 'Satu kode cadangan telah dipakai dan tidak berlaku lagi.');
        }

        return redirect()->intended(route('dasbor'));
    }

    /**
     * The challenge only applies to users with confirmed 2FA who have not verified this session.
     */
    private function isSatisfied(Request $request): bool
    {
        return ! $request->user()->hasConfirmedTwoFactor()
            || $request->session()->get(EnsureTwoFactorIsVerified::SessionKey) === true;
    }
}
