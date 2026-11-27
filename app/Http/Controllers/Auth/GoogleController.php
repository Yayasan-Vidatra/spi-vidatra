<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Login SSO Google, satu-satunya cara masuk ke aplikasi (US-01).
 */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')
            ->with([
                'hd' => config('services.google.allowed_domain'),
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->rejectLogin('Login dengan Google dibatalkan.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->rejectLogin('Sesi login kedaluwarsa. Silakan coba lagi.');
        }

        if (! $this->isFoundationAccount($googleUser)) {
            return $this->rejectLogin('Hanya akun @'.config('services.google.allowed_domain').' yang dapat masuk.');
        }

        $user = $this->findOrRegisterUser($googleUser);

        if ($user === null) {
            return $this->rejectLogin('Akun Google ini tidak cocok dengan akun yang terdaftar. Hubungi Admin IT Yayasan.');
        }

        if (! $user->is_active) {
            return $this->rejectLogin('Akun Anda tidak aktif. Hubungi Admin IT Yayasan.');
        }

        // Tanpa peran, atau PIC Unit tanpa unit (US-16).
        if (! $user->canSignIn()) {
            Inertia::flash('email', $user->email);

            return redirect()->route('akses.belum-diberi');
        }

        $request->session()->forget(EnsureTwoFactorIsVerified::SessionKey);

        Auth::login($user);

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dasbor'));
    }

    /**
     * Google menandai akun Workspace dengan klaim `hd`; akhiran email saja tidak cukup
     * karena akun Google pribadi bisa memakai alamat dari domain lain.
     */
    private function isFoundationAccount(SocialiteUser $googleUser): bool
    {
        $domain = config('services.google.allowed_domain');
        $raw = $googleUser->getRaw();

        return Str::endsWith(Str::lower((string) $googleUser->getEmail()), '@'.$domain)
            && ($raw['hd'] ?? null) === $domain
            && filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Find the account linked to this Google identity, linking or registering it on first login.
     *
     * Returns null when the email is already linked to a different Google identity.
     */
    private function findOrRegisterUser(SocialiteUser $googleUser): ?User
    {
        $googleId = (string) $googleUser->getId();
        $email = Str::lower((string) $googleUser->getEmail());

        $user = User::query()->where('google_id', $googleId)->first()
            ?? User::query()->where('email', $email)->first();

        if ($user === null) {
            return User::query()->create([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'google_id' => $googleId,
            ]);
        }

        if ($user->google_id !== null && $user->google_id !== $googleId) {
            return null;
        }

        $user->forceFill(['google_id' => $googleId, 'email' => $email])->save();

        return $user;
    }

    private function rejectLogin(string $message): RedirectResponse
    {
        Inertia::flash('error', $message);

        return redirect()->route('login');
    }
}
