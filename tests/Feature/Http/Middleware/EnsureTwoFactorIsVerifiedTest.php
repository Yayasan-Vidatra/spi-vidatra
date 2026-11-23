<?php

namespace Tests\Feature\Http\Middleware;

use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnsureTwoFactorIsVerifiedTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{RoleSlug}>
     */
    public static function rolesRequiringTwoFactor(): array
    {
        return [
            'dewan pengawas' => [RoleSlug::DewanPengawas],
            'auditor spi' => [RoleSlug::AuditorSpi],
            'super admin' => [RoleSlug::SuperAdmin],
        ];
    }

    /**
     * @return array<string, array{RoleSlug}>
     */
    public static function rolesWithoutTwoFactorRequirement(): array
    {
        return [
            'pic unit' => [RoleSlug::PicUnit],
            'pengurus yayasan' => [RoleSlug::PengurusYayasan],
            'peninjau tamu' => [RoleSlug::PeninjauTamu],
        ];
    }

    #[DataProvider('rolesRequiringTwoFactor')]
    public function test_redirects_role_requiring_two_factor_to_activation_before_any_page(RoleSlug $roleSlug): void
    {
        $user = User::factory()->withRole($roleSlug)->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)->get(route('dasbor'));

        $response->assertRedirect(route('dua-faktor.aktivasi'));
    }

    #[DataProvider('rolesWithoutTwoFactorRequirement')]
    public function test_lets_role_without_two_factor_requirement_through(RoleSlug $roleSlug): void
    {
        $user = User::factory()->withRole($roleSlug)->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)->get(route('dasbor'));

        $response->assertOk();
    }

    public function test_activation_comes_before_the_confidentiality_statement(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->create();

        $response = $this->actingAs($user)->get(route('kerahasiaan.show'));

        $response->assertRedirect(route('dua-faktor.aktivasi'));
    }

    public function test_redirects_user_with_confirmed_two_factor_to_the_challenge_on_a_new_session(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->withTwoFactor()->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)->get(route('dasbor'));

        $response->assertRedirect(route('dua-faktor.verifikasi'));
    }

    public function test_lets_user_through_after_two_factor_is_verified_in_this_session(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->withTwoFactor()->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)
            ->withSession([EnsureTwoFactorIsVerified::SessionKey => true])
            ->get(route('dasbor'));

        $response->assertOk();
    }

    public function test_panel_requires_two_factor_activation_for_super_admin(): void
    {
        $user = User::factory()->withRole(RoleSlug::SuperAdmin)->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertRedirect(route('dua-faktor.aktivasi'));
    }

    public function test_panel_requires_two_factor_challenge_for_super_admin(): void
    {
        $user = User::factory()->withRole(RoleSlug::SuperAdmin)->withTwoFactor()->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertRedirect(route('dua-faktor.verifikasi'));
    }
}
