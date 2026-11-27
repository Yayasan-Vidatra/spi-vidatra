<?php

use App\Http\Controllers\AssignmentClosureController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AssignmentMemberController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\ConfidentialityStatementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentRequestController;
use App\Http\Controllers\DocumentRequestDueDateController;
use App\Http\Controllers\FileContentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileVersionController;
use App\Http\Controllers\FolderArchiveController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\GeneralFolderController;
use App\Http\Controllers\NotificationReadController;
use App\Http\Controllers\RequestItemReviewController;
use App\Http\Controllers\RequestItemUploadController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorRecoveryCodeController;
use App\Http\Controllers\TwoFactorSetupController;
use App\Http\Controllers\UnitDocumentRequestController;
use App\Http\Middleware\EnsureAccountHasAccess;
use App\Http\Middleware\EnsureConfidentialityStatementAccepted;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Services\FileContentUrl;
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
            Route::get('/dasbor', [DashboardController::class, 'show'])->name('dasbor');
            Route::post('/notifikasi/dibaca', [NotificationReadController::class, 'storeAll'])->name('notifikasi.dibaca-semua');
            Route::get('/notifikasi/{notification}/buka', [NotificationReadController::class, 'show'])
                ->whereUuid('notification')
                ->name('notifikasi.buka');
            Route::post('/notifikasi/{notification}/dibaca', [NotificationReadController::class, 'store'])
                ->whereUuid('notification')
                ->name('notifikasi.dibaca');

            Route::get('/penugasan', [AssignmentController::class, 'index'])->name('penugasan.index');
            Route::get('/penugasan/buat', [AssignmentController::class, 'create'])->name('penugasan.create');
            Route::post('/penugasan', [AssignmentController::class, 'store'])->name('penugasan.store');
            Route::get('/penugasan/{assignment}', [AssignmentController::class, 'show'])
                ->whereNumber('assignment')
                ->name('penugasan.show');

            // Scoped binding: auditor atau folder dari penugasan lain menghasilkan 404.
            Route::prefix('penugasan/{assignment}')
                ->whereNumber('assignment')
                ->name('penugasan.')
                ->scopeBindings()
                ->group(function () {
                    Route::post('/penutupan', [AssignmentClosureController::class, 'store'])->name('penutupan.store');
                    Route::delete('/penutupan', [AssignmentClosureController::class, 'destroy'])->name('penutupan.destroy');

                    Route::post('/auditor', [AssignmentMemberController::class, 'store'])->name('auditor.store');
                    Route::patch('/auditor/{member}', [AssignmentMemberController::class, 'update'])->name('auditor.update');
                    Route::delete('/auditor/{member}', [AssignmentMemberController::class, 'destroy'])->name('auditor.destroy');

                    Route::post('/folder', [FolderController::class, 'store'])->name('folder.store');
                    Route::patch('/folder/{folder}', [FolderController::class, 'update'])->name('folder.update');
                    Route::post('/folder/{folder}/arsip', [FolderArchiveController::class, 'store'])->name('folder.arsip.store');

                    // H-07 Permintaan Dokumen (SPI); permintaan dari penugasan lain menghasilkan 404.
                    Route::get('/permintaan', [DocumentRequestController::class, 'index'])->name('permintaan.index');
                    Route::get('/permintaan/buat', [DocumentRequestController::class, 'create'])->name('permintaan.create');
                    Route::post('/permintaan', [DocumentRequestController::class, 'store'])->name('permintaan.store');
                    Route::get('/permintaan/{documentRequest}', [DocumentRequestController::class, 'show'])
                        ->whereNumber('documentRequest')
                        ->name('permintaan.show');
                    Route::patch('/permintaan/{documentRequest}/tenggat', [DocumentRequestDueDateController::class, 'update'])
                        ->whereNumber('documentRequest')
                        ->name('permintaan.tenggat.update');
                    Route::post('/permintaan/{documentRequest}/slot/{item}/periksa', [RequestItemReviewController::class, 'store'])
                        ->whereNumber(['documentRequest', 'item'])
                        ->name('permintaan.slot.periksa');
                });

            // H-08 Permintaan untuk Unit Saya (PIC Unit). Scoped binding: slot dari permintaan lain menghasilkan 404.
            Route::get('/permintaan-unit', [UnitDocumentRequestController::class, 'index'])->name('permintaan-unit.index');
            Route::prefix('permintaan-unit/{documentRequest}')
                ->whereNumber('documentRequest')
                ->name('permintaan-unit.')
                ->scopeBindings()
                ->group(function () {
                    Route::get('/', [UnitDocumentRequestController::class, 'show'])->name('show');
                    Route::post('/slot/{item}', [RequestItemUploadController::class, 'store'])
                        ->whereNumber('item')
                        ->middleware('throttle:berkas-unggah')
                        ->name('slot.unggah');
                });

            Route::get('/folder-umum', [GeneralFolderController::class, 'index'])->name('folder-umum.index');

            Route::get('/cari', [SearchController::class, 'index'])->name('cari.index');

            // H-14: rute ini memuat berkas di Tempat Sampah; controller menolak berkas yang belum dihapus.
            Route::get('/tempat-sampah', [TrashController::class, 'index'])->name('tempat-sampah.index');
            Route::post('/tempat-sampah/{file}/pulihkan', [TrashController::class, 'restore'])
                ->whereNumber('file')
                ->withTrashed()
                ->name('tempat-sampah.pulihkan');
            Route::delete('/tempat-sampah/{file}', [TrashController::class, 'destroy'])
                ->whereNumber('file')
                ->withTrashed()
                ->name('tempat-sampah.destroy');

            Route::post('/folder/{folder}/berkas', [FileController::class, 'store'])
                ->whereNumber('folder')
                ->middleware('throttle:berkas-unggah')
                ->name('folder.berkas.store');

            // Scoped binding: versi dari berkas lain menghasilkan 404.
            Route::prefix('berkas/{file}')
                ->whereNumber('file')
                ->name('berkas.')
                ->scopeBindings()
                ->group(function () {
                    Route::get('/', [FileController::class, 'show'])->name('show');
                    Route::patch('/', [FileController::class, 'update'])->name('update');
                    Route::delete('/', [FileController::class, 'destroy'])->name('destroy');

                    Route::post('/versi/{version}/pulihkan', [FileVersionController::class, 'restore'])
                        ->whereNumber('version')
                        ->name('versi.pulihkan');
                    Route::get('/versi/{version}/unduh', [FileVersionController::class, 'download'])
                        ->whereNumber('version')
                        ->name('versi.unduh');
                    Route::get('/versi/{version}/pratinjau', [FileVersionController::class, 'preview'])
                        ->whereNumber('version')
                        ->name('versi.pratinjau');

                    // Satu-satunya jalur isi berkas: signed URL 5 menit milik pengguna dan sesinya (US-11).
                    Route::get('/versi/{version}/konten/{mode}', [FileContentController::class, 'show'])
                        ->whereNumber('version')
                        ->whereIn('mode', [FileContentUrl::ModePreview, FileContentUrl::ModeDownload])
                        ->middleware(['signed', 'throttle:berkas-unduh'])
                        ->name('konten');
                });
        });
    });
});
