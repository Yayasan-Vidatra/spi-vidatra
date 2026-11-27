<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithGoogleLogin;
use Tests\TestCase;

class GoogleControllerTest extends TestCase
{
    use InteractsWithGoogleLogin;
    use LazilyRefreshDatabase;

    public function test_redirect_sends_user_to_google_restricted_to_the_foundation_domain(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $response = $this->get(route('auth.google.redirect'));

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/auth');
        $response->assertRedirectContains('hd=vidatra.sch.id');
    }

    public function test_domain_account_with_role_is_logged_in_and_last_login_is_recorded(): void
    {
        $this->freezeTime();
        $user = User::factory()->withRole(RoleSlug::PicUnit)->create(['email' => 'siti@vidatra.sch.id', 'google_id' => '109876543210']);

        $response = $this->loginWithGoogle($this->googleUser('siti@vidatra.sch.id'));

        $response->assertRedirect(route('dasbor'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(now()->toDateTimeString(), $user->fresh()->last_login_at->toDateTimeString());
    }

    public function test_first_login_links_google_identity_to_account_registered_by_email(): void
    {
        $user = User::factory()->withRole(RoleSlug::PicUnit)->create(['email' => 'siti@vidatra.sch.id', 'google_id' => null]);

        $this->loginWithGoogle($this->googleUser('Siti@Vidatra.sch.id', '555000111222'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('555000111222', $user->fresh()->google_id);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    #[DataProvider('nonFoundationAccounts')]
    public function test_rejects_account_outside_the_foundation_workspace(string $email, array $claims): void
    {
        $response = $this->loginWithGoogle($this->googleUser($email, claims: $claims));

        $response->assertRedirect(route('login'));
        $response->assertInertiaFlash('error', 'Hanya akun @vidatra.sch.id yang dapat masuk.');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function nonFoundationAccounts(): array
    {
        return [
            'personal gmail account' => ['siti@gmail.com', ['hd' => null]],
            'other workspace domain' => ['siti@sekolahlain.sch.id', ['hd' => 'sekolahlain.sch.id']],
            'foundation email without workspace claim' => ['siti@vidatra.sch.id', ['hd' => null]],
            'lookalike subdomain' => ['siti@palsu.vidatra.sch.id', ['hd' => 'palsu.vidatra.sch.id']],
            'unverified email' => ['siti@vidatra.sch.id', ['email_verified' => false]],
        ];
    }

    public function test_domain_account_without_role_sees_pending_access_page_without_being_logged_in(): void
    {
        $response = $this->loginWithGoogle($this->googleUser('baru@vidatra.sch.id'));

        $response->assertRedirect(route('akses.belum-diberi'));
        $response->assertInertiaFlash('email', 'baru@vidatra.sch.id');
        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'baru@vidatra.sch.id',
            'google_id' => '109876543210',
            'role_id' => null,
            'is_active' => true,
        ]);
    }

    public function test_pic_unit_without_a_unit_sees_pending_access_page_without_being_logged_in(): void
    {
        User::factory()->withRole(RoleSlug::PicUnit)->create([
            'email' => 'siti@vidatra.sch.id',
            'google_id' => '109876543210',
            'unit_id' => null,
        ]);

        $response = $this->loginWithGoogle($this->googleUser('siti@vidatra.sch.id'));

        $response->assertRedirect(route('akses.belum-diberi'));
        $response->assertInertiaFlash('email', 'siti@vidatra.sch.id');
        $this->assertGuest();
    }

    public function test_pending_access_page_renders_the_not_yet_granted_message(): void
    {
        $this->loginWithGoogle($this->googleUser('baru@vidatra.sch.id'));

        $response = $this->get(route('akses.belum-diberi'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/BelumDiberiAkses')
            ->hasFlash('email', 'baru@vidatra.sch.id'));
    }

    public function test_rejects_inactive_account(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->inactive()->create(['email' => 'siti@vidatra.sch.id']);

        $response = $this->loginWithGoogle($this->googleUser('siti@vidatra.sch.id', $user->google_id));

        $response->assertRedirect(route('login'));
        $response->assertInertiaFlash('error', 'Akun Anda tidak aktif. Hubungi Admin IT Yayasan.');
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_rejects_email_already_linked_to_a_different_google_identity(): void
    {
        $user = User::factory()->withRole(RoleSlug::PicUnit)->create(['email' => 'siti@vidatra.sch.id', 'google_id' => '111111111111']);

        $response = $this->loginWithGoogle($this->googleUser('siti@vidatra.sch.id', '222222222222'));

        $response->assertRedirect(route('login'));
        $response->assertInertiaFlash('error');
        $this->assertGuest();
        $this->assertSame('111111111111', $user->fresh()->google_id);
    }

    public function test_expired_oauth_state_returns_to_login_with_message(): void
    {
        Socialite::fake('google', fn () => throw new InvalidStateException);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertInertiaFlash('error', 'Sesi login kedaluwarsa. Silakan coba lagi.');
        $this->assertGuest();
    }

    public function test_cancelled_google_consent_returns_to_login_with_message(): void
    {
        $response = $this->get(route('auth.google.callback', ['error' => 'access_denied']));

        $response->assertRedirect(route('login'));
        $response->assertInertiaFlash('error', 'Login dengan Google dibatalkan.');
        $this->assertGuest();
    }
}
