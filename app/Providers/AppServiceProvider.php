<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Rate limit login dan verifikasi 2FA (NF-01).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
