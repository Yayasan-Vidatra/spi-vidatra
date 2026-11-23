<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorChallengeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_challenge_page_renders_for_user_with_confirmed_two_factor(): void
    {
        $user = User::factory()->withRole(RoleSlug::DewanPengawas)->withTwoFactor()->create();

        $response = $this->actingAs($user)->get(route('dua-faktor.verifikasi'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Keamanan/VerifikasiDuaFaktor'));
    }

    public function test_valid_code_verifies_the_session(): void
    {
        $user = User::factory()->withRole(RoleSlug::DewanPengawas)->withTwoFactor()->acceptedConfidentiality()->create();
        $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);

        $response = $this->actingAs($user)->post(route('dua-faktor.verifikasi.store'), ['code' => $code]);

        $response->assertRedirect(route('dasbor'));
        $response->assertSessionHas(EnsureTwoFactorIsVerified::SessionKey, true);
        $this->assertNotNull($user->fresh()->two_factor_last_used_timestep);
    }

    public function test_rejects_invalid_code(): void
    {
        $user = User::factory()->withRole(RoleSlug::DewanPengawas)->withTwoFactor()->create();

        $response = $this->actingAs($user)
            ->from(route('dua-faktor.verifikasi'))
            ->post(route('dua-faktor.verifikasi.store'), ['code' => '000000']);

        $response->assertRedirect(route('dua-faktor.verifikasi'));
        $response->assertSessionHasErrors(['code' => 'Kode tidak valid.']);
        $response->assertSessionMissing(EnsureTwoFactorIsVerified::SessionKey);
    }

    public function test_rejects_replay_of_an_already_used_code(): void
    {
        $user = User::factory()->withRole(RoleSlug::DewanPengawas)->withTwoFactor()->create();
        $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);
        $this->actingAs($user)->post(route('dua-faktor.verifikasi.store'), ['code' => $code]);
        $this->flushSession();

        $response = $this->actingAs($user->fresh())->post(route('dua-faktor.verifikasi.store'), ['code' => $code]);

        $response->assertSessionHasErrors(['code' => 'Kode tidak valid.']);
        $response->assertSessionMissing(EnsureTwoFactorIsVerified::SessionKey);
    }

    public function test_recovery_code_verifies_the_session_once(): void
    {
        $user = User::factory()->withRole(RoleSlug::DewanPengawas)->withTwoFactor()->create();

        $response = $this->actingAs($user)->post(route('dua-faktor.verifikasi.store'), [
            'recovery_code' => strtolower(UserFactory::RecoveryCode),
        ]);

        $response->assertRedirect(route('dasbor'));
        $response->assertSessionHas(EnsureTwoFactorIsVerified::SessionKey, true);
        $response->assertInertiaFlash('warning', 'Satu kode cadangan telah dipakai dan tidak berlaku lagi.');
        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_rejects_recovery_code_that_was_already_used(): void
    {
        $user = User::factory()->withRole(RoleSlug::DewanPengawas)->withTwoFactor()->create();
        $this->actingAs($user)->post(route('dua-faktor.verifikasi.store'), ['recovery_code' => UserFactory::RecoveryCode]);
        $this->flushSession();

        $response = $this->actingAs($user->fresh())->post(route('dua-faktor.verifikasi.store'), [
            'recovery_code' => UserFactory::RecoveryCode,
        ]);

        $response->assertSessionHasErrors(['recovery_code' => 'Kode cadangan tidak valid atau sudah dipakai.']);
        $response->assertSessionMissing(EnsureTwoFactorIsVerified::SessionKey);
    }

    public function test_requires_a_code_or_recovery_code(): void
    {
        $user = User::factory()->withRole(RoleSlug::DewanPengawas)->withTwoFactor()->create();

        $response = $this->actingAs($user)->post(route('dua-faktor.verifikasi.store'), []);

        $response->assertSessionHasErrors(['code' => 'Masukkan kode 6 digit atau kode cadangan.']);
    }

    public function test_throttles_repeated_attempts_with_429(): void
    {
        $user = User::factory()->withRole(RoleSlug::DewanPengawas)->withTwoFactor()->create();

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($user)->post(route('dua-faktor.verifikasi.store'), ['code' => '000000']);
        }
        $response = $this->actingAs($user)->post(route('dua-faktor.verifikasi.store'), ['code' => '000000']);

        $response->assertTooManyRequests();
    }
}
