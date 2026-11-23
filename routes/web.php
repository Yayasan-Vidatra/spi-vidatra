<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\ConfidentialityStatementController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorRecoveryCodeController;
use App\Http\Controllers\TwoFactorSetupController;
use App\Http\Middleware\EnsureAccountHasAccess;
use App\Http\Middleware\EnsureConfidentialityStatementAccepted;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dasbor')->name('beranda');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

    Route::middleware('throttle:login')->group(function () {
        Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
        Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
    });
});

Route::inertia('/belum-diberi-akses', 'Auth/BelumDiberiAkses')->name('akses.belum-diberi');

Route::middleware(['auth', EnsureAccountHasAccess::class])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('keamanan/dua-faktor')->name('dua-faktor.')->group(function () {
        Route::get('/aktivasi', [TwoFactorSetupController::class, 'show'])->name('aktivasi');
        Route::post('/aktivasi', [TwoFactorSetupController::class, 'store'])
            ->middleware('throttle:two-factor')
            ->name('aktivasi.store');

        Route::get('/verifikasi', [TwoFactorChallengeController::class, 'show'])->name('verifikasi');
        Route::post('/verifikasi', [TwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:two-factor')
            ->name('verifikasi.store');
    });

    Route::middleware(EnsureTwoFactorIsVerified::class)->group(function () {
        Route::get('/keamanan/dua-faktor/kode-cadangan', [TwoFactorRecoveryCodeController::class, 'show'])
            ->name('dua-faktor.kode-cadangan');

        Route::get('/keamanan/pernyataan-kerahasiaan', [ConfidentialityStatementController::class, 'show'])
            ->name('kerahasiaan.show');
        Route::post('/keamanan/pernyataan-kerahasiaan', [ConfidentialityStatementController::class, 'store'])
            ->name('kerahasiaan.store');

        Route::middleware(EnsureConfidentialityStatementAccepted::class)->group(function () {
            Route::inertia('/dasbor', 'Dasbor')->name('dasbor');
        });
    });
});
