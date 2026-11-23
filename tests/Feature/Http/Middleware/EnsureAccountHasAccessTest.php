<?php

namespace Tests\Feature\Http\Middleware;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EnsureAccountHasAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_logs_out_user_whose_account_was_deactivated_after_login(): void
    {
        $user = User::factory()->withRole(RoleSlug::PicUnit)->acceptedConfidentiality()->inactive()->create();

        $response = $this->actingAs($user)->get(route('dasbor'));

        $response->assertRedirect(route('login'));
        $response->assertInertiaFlash('error', 'Akun Anda tidak aktif. Hubungi Admin IT Yayasan.');
        $this->assertGuest();
    }

    public function test_logs_out_user_whose_role_was_removed_after_login(): void
    {
        $user = User::factory()->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)->get(route('dasbor'));

        $response->assertRedirect(route('akses.belum-diberi'));
        $this->assertGuest();
    }

    public function test_deactivated_super_admin_cannot_open_the_panel(): void
    {
        $user = User::factory()->withRole(RoleSlug::SuperAdmin)->withTwoFactor()->acceptedConfidentiality()->inactive()->create();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
