<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_renders_google_sign_in(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_guest_is_redirected_to_login_from_the_dashboard(): void
    {
        $response = $this->get(route('dasbor'));

        $response->assertRedirect(route('login'));
    }

    public function test_root_url_leads_to_the_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dasbor');
    }

    public function test_local_password_login_is_not_available(): void
    {
        $response = $this->post('/login', ['email' => 'siti@vidatra.sch.id', 'password' => 'password']);

        $response->assertMethodNotAllowed();
        $this->assertGuest();
    }

    #[DataProvider('passwordRoutes')]
    public function test_password_routes_do_not_exist(string $method, string $uri): void
    {
        $response = $this->call($method, $uri);

        $response->assertNotFound();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function passwordRoutes(): array
    {
        return [
            'register page' => ['GET', '/register'],
            'forgot password page' => ['GET', '/forgot-password'],
            'reset password page' => ['GET', '/reset-password/token'],
            'panel login page' => ['GET', '/panel/login'],
            'panel password reset page' => ['GET', '/panel/password-reset/request'],
        ];
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->withRole(RoleSlug::PicUnit)->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
