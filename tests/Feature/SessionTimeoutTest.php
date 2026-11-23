<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithGoogleLogin;
use Tests\TestCase;

/**
 * US-04: sesi habis setelah 30 menit tidak aktif.
 *
 * Memakai driver sesi database seperti produksi dan mengirim ulang cookie sesi,
 * sehingga setiap permintaan dibaca dari penyimpanan sesi seperti peramban sungguhan.
 */
class SessionTimeoutTest extends TestCase
{
    use InteractsWithGoogleLogin;
    use LazilyRefreshDatabase;

    public function test_session_expires_after_30_minutes_of_inactivity(): void
    {
        $this->freezeTime();
        $sessionId = $this->loginAndGetSessionId();

        $this->travel(31)->minutes();
        $response = $this->getDashboardWithSession($sessionId);

        $response->assertRedirect(route('login'));
    }

    public function test_activity_within_30_minutes_keeps_the_session_alive(): void
    {
        $this->freezeTime();
        $sessionId = $this->loginAndGetSessionId();

        $this->travel(29)->minutes();
        $this->getDashboardWithSession($sessionId)->assertOk();
        $this->travel(29)->minutes();
        $response = $this->getDashboardWithSession($sessionId);

        $response->assertOk();
    }

    private function loginAndGetSessionId(): string
    {
        config(['session.driver' => 'database']);
        $this->app['session']->forgetDrivers();

        $user = User::factory()->withRole(RoleSlug::PicUnit)->acceptedConfidentiality()->create(['email' => 'siti@vidatra.sch.id']);

        $response = $this->loginWithGoogle($this->googleUser('siti@vidatra.sch.id', $user->google_id));

        return $response->getCookie(config('session.cookie'))->getValue();
    }

    /**
     * Simulate a new browser request: drop the in-memory session and auth state so
     * only the session cookie and the stored session decide whether the user is logged in.
     */
    private function getDashboardWithSession(string $sessionId): TestResponse
    {
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['auth']->forgetGuards();

        return $this->withCookie(config('session.cookie'), $sessionId)->get(route('dasbor'));
    }
}
