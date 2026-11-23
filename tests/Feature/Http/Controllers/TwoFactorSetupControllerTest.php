<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorSetupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('dua-faktor.aktivasi'));

        $response->assertRedirect(route('login'));
    }

    public function test_activation_page_shows_qr_code_and_stores_the_secret_encrypted(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->create();

        $response = $this->actingAs($user)->get(route('dua-faktor.aktivasi'));

        $secret = $user->fresh()->two_factor_secret;
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Keamanan/AktivasiDuaFaktor')
            ->where('secret', $secret)
            ->where('qrCode', fn (string $qrCode): bool => str_starts_with($qrCode, 'data:image/svg+xml;base64,')));

        $storedValue = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $storedValue);
        $this->assertStringNotContainsString($secret, $storedValue);
        $this->assertSame($secret, Crypt::decryptString($storedValue));
    }

    public function test_reloading_the_activation_page_keeps_the_same_secret(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $this->actingAs($user)->get(route('dua-faktor.aktivasi'));
        $firstSecret = $user->fresh()->two_factor_secret;

        $this->actingAs($user)->get(route('dua-faktor.aktivasi'));

        $this->assertSame($firstSecret, $user->fresh()->two_factor_secret);
    }

    public function test_valid_code_activates_two_factor_and_issues_one_time_recovery_codes(): void
    {
        $this->freezeTime();
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $this->actingAs($user)->get(route('dua-faktor.aktivasi'));
        $code = (new Google2FA)->getCurrentOtp($user->fresh()->two_factor_secret);

        $response = $this->actingAs($user)->post(route('dua-faktor.aktivasi.store'), ['code' => $code]);

        $response->assertRedirect(route('dua-faktor.kode-cadangan'));
        $user->refresh();
        $this->assertSame(now()->toDateTimeString(), $user->two_factor_confirmed_at->toDateTimeString());

        $recoveryCodes = session(SessionKey::FLASH_DATA)['recoveryCodes'];
        $this->assertCount(8, $recoveryCodes);
        $this->assertCount(8, $user->two_factor_recovery_codes);
        $this->assertNotContains($recoveryCodes[0], $user->two_factor_recovery_codes);
        $this->assertTrue(Hash::check($recoveryCodes[0], $user->two_factor_recovery_codes[0]));
    }

    public function test_recovery_codes_page_renders_the_flashed_codes_after_activation(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $this->actingAs($user)->get(route('dua-faktor.aktivasi'));
        $code = (new Google2FA)->getCurrentOtp($user->fresh()->two_factor_secret);

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->post(route('dua-faktor.aktivasi.store'), ['code' => $code]);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Keamanan/KodeCadangan')
            ->hasFlash('recoveryCodes'));
    }

    public function test_activation_unlocks_the_next_gate(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $this->actingAs($user)->get(route('dua-faktor.aktivasi'));
        $code = (new Google2FA)->getCurrentOtp($user->fresh()->two_factor_secret);
        $this->actingAs($user)->post(route('dua-faktor.aktivasi.store'), ['code' => $code]);

        $response = $this->actingAs($user)->get(route('dasbor'));

        $response->assertRedirect(route('kerahasiaan.show'));
    }

    public function test_rejects_invalid_code(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $this->actingAs($user)->get(route('dua-faktor.aktivasi'));

        $response = $this->actingAs($user)
            ->from(route('dua-faktor.aktivasi'))
            ->post(route('dua-faktor.aktivasi.store'), ['code' => '000000']);

        $response->assertRedirect(route('dua-faktor.aktivasi'));
        $response->assertSessionHasErrors(['code' => 'Kode tidak valid. Pastikan jam di perangkat Anda tepat, lalu coba lagi.']);
        $user->refresh();
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_rejects_code_that_is_not_six_digits(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->create();

        $response = $this->actingAs($user)->post(route('dua-faktor.aktivasi.store'), ['code' => '12ab']);

        $response->assertSessionHasErrors(['code' => 'Kode harus 6 digit angka.']);
    }

    public function test_confirmed_user_cannot_replace_their_secret_through_activation(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->withTwoFactor()->create();
        $originalSecret = $user->two_factor_secret;

        $response = $this->actingAs($user)->get(route('dua-faktor.aktivasi'));

        $response->assertRedirect(route('dasbor'));
        $this->assertSame($originalSecret, $user->fresh()->two_factor_secret);
    }
}
