<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

trait InteractsWithGoogleLogin
{
    /**
     * Build the user Google returns from its userinfo endpoint.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function googleUser(string $email, string $googleId = '109876543210', array $claims = []): GoogleUser
    {
        $raw = [
            'sub' => $googleId,
            'email' => $email,
            'email_verified' => true,
            'hd' => 'vidatra.sch.id',
            'name' => 'Siti Rahmawati',
            ...$claims,
        ];

        return (new GoogleUser)->setRaw($raw)->map([
            'id' => $raw['sub'],
            'name' => $raw['name'],
            'email' => $raw['email'],
        ]);
    }

    protected function loginWithGoogle(GoogleUser $googleUser): TestResponse
    {
        Socialite::fake('google', $googleUser);

        return $this->get(route('auth.google.callback'));
    }
}
