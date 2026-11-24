<?php

namespace App\Providers;

use App\Models\Assignment;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\Scanning\ClamAvScanner;
use App\Services\Scanning\LocalScanner;
use App\Services\Scanning\VirusScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
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
        $this->app->bind(VirusScanner::class, fn (Application $app): VirusScanner => match (config('berkas.scanner')) {
            'clamav' => new ClamAvScanner(config('berkas.clamav.socket'), config('berkas.clamav.timeout')),
            'lokal' => new LocalScanner($app->environment()),
        });
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
            'berkas' => File::class,
            'pengguna' => User::class,
        ]);
    }

    /**
     * Rate limit login, verifikasi 2FA, unggah, dan unduh (NF-01).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('berkas-unggah', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('berkas-unduh', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
