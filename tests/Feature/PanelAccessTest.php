<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_panel_to_google_login(): void
    {
        $response = $this->get('/panel');

        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_who_passed_every_gate_can_open_the_panel(): void
    {
        $user = User::factory()->withRole(RoleSlug::SuperAdmin)->withTwoFactor()->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)
            ->withSession([EnsureTwoFactorIsVerified::SessionKey => true])
            ->get('/panel');

        $response->assertOk();
    }

    public function test_forbids_panel_for_roles_other_than_super_admin(): void
    {
        $user = User::factory()->withRole(RoleSlug::DewanPengawas)->withTwoFactor()->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)
            ->withSession([EnsureTwoFactorIsVerified::SessionKey => true])
            ->get('/panel');

        $response->assertForbidden();
    }
}
