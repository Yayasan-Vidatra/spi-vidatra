<?php

namespace App\Providers;

use App\Models\Assignment;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        // Nama objek yang stabil di log audit, tidak bergantung pada nama class PHP.
        Relation::morphMap([
            'penugasan' => Assignment::class,
            'folder' => Folder::class,
            'pengguna' => User::class,
        ]);
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
