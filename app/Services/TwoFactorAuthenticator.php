<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * 2FA aplikasi berbasis TOTP (US-02).
 */
class TwoFactorAuthenticator
{
    /**
     * Jumlah langkah 30 detik yang masih diterima di kiri-kanan waktu server.
     */
    private const int VerificationWindow = 1;

    private const int RecoveryCodeCount = 8;

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Build an inline SVG data URI of the otpauth QR code for an authenticator app.
     */
    public function qrCodeDataUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeInline(config('app.name'), $user->email, $secret);
    }

    /**
     * Verify a TOTP code and remember its time step so the same code cannot be replayed.
     */
    public function verifyCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        $timestep = $this->google2fa->verifyKeyNewer(
            $user->two_factor_secret,
            $code,
            $user->two_factor_last_used_timestep ?? 0,
            self::VerificationWindow,
        );

        if ($timestep === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_timestep' => $timestep])->save();

        return true;
    }

    /**
     * Generate fresh one-time recovery codes, store their hashes, and return the plain codes.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = Collection::times(
            self::RecoveryCodeCount,
            fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)),
        )->all();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $codes),
        ])->save();

        return $codes;
    }

    /**
     * Consume a recovery code. Each code works only once.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::upper(trim($code));
        $hashedCodes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashedCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($hashedCodes[$index]);

                $user->forceFill(['two_factor_recovery_codes' => array_values($hashedCodes)])->save();

                return true;
            }
        }

        return false;
    }
}
