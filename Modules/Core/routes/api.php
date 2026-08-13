<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\CapabilityController;
use Modules\Core\Http\Controllers\HealthController;
use Modules\Core\Http\Controllers\MasterDataController;
use Modules\Core\Http\Controllers\ProfileController;
use Modules\Core\Http\Controllers\ProviderController;
use Modules\Core\Http\Controllers\SessionController;

/*
|--------------------------------------------------------------------------
| Core Module API Routes
|--------------------------------------------------------------------------
| Mounted at /api/v1/core/* (see app/Providers/RouteServiceProvider.php).
| Add module endpoints here — see docs/modules/core.md for the entity map
| and .claude/skills/laravel-endpoint for the scaffold-a-new-endpoint flow.
|
| Health checks and master data stay outside auth — load balancers, uptime
| monitors, and unauthenticated pickers (country/city/currency selects on a
| registration form) must be able to hit them unauthenticated.
*/

Route::prefix('v1/core')->name('core.')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'module' => 'core']))->name('health');
    Route::get('/health/ready', HealthController::class.'@ready')->name('health.ready');

    Route::get('/countries', [MasterDataController::class, 'countries'])->name('countries.index');
    Route::get('/countries/{country}/cities', [MasterDataController::class, 'cities'])->name('countries.cities');
    Route::get('/currencies', [MasterDataController::class, 'currencies'])->name('currencies.index');

    // --- Auth: public ---
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:login')->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset')->name('auth.forgot-password');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('auth.reset-password');

    // --- Auth + everything else: authenticated (Sanctum) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/verify', [AuthController::class, 'verify'])->middleware('throttle:verification-code-consume')->name('auth.verify');
        Route::post('/auth/resend-code', [AuthController::class, 'resendCode'])->middleware('throttle:verification-code')->name('auth.resend-code');
        Route::put('/auth/password', [AuthController::class, 'changePassword'])->middleware('throttle:password-reset')->name('auth.password.update');

        Route::get('/auth/sessions', [SessionController::class, 'index'])->name('auth.sessions.index');
        Route::delete('/auth/sessions/{session}', [SessionController::class, 'destroy'])->name('auth.sessions.destroy');

        Route::get('/me/capabilities', [CapabilityController::class, 'show'])->name('me.capabilities');

        // Profile management is not gated behind account verification — a user can manage their
        // own basic account details before verifying; verification only gates trust-bearing
        // actions (Provider identity, below). `mutations` is the general per-user write limiter —
        // see docs/api/conventions.md § Rate limiting.
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::middleware('throttle:mutations')->group(function () {
            Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.store');
            Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
            Route::put('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.language');
            Route::put('/profile/currency', [ProfileController::class, 'updateCurrency'])->name('profile.currency');
        });

        // FR-PROVIDER-*: activating a provider identity and submitting it for verification are
        // trust-bearing actions — require a verified account first (see EnsureAccountIsVerified).
        Route::middleware(['account.verified', 'throttle:mutations'])->group(function () {
            Route::get('/providers/me', [ProviderController::class, 'show'])->name('providers.me');
            Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
            Route::put('/providers/me', [ProviderController::class, 'update'])->name('providers.update');
            Route::post('/providers/me/logo', [ProviderController::class, 'updateLogo'])->name('providers.logo.store');
            Route::delete('/providers/me/logo', [ProviderController::class, 'destroyLogo'])->name('providers.logo.destroy');
            Route::post('/providers/me/cover', [ProviderController::class, 'updateCover'])->name('providers.cover.store');
            Route::delete('/providers/me/cover', [ProviderController::class, 'destroyCover'])->name('providers.cover.destroy');
            Route::post('/providers/me/verification', [ProviderController::class, 'submitVerification'])->name('providers.verification.store');
        });
    });
});
