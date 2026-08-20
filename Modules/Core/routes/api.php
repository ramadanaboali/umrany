<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\CapabilityController;
use Modules\Core\Http\Controllers\HealthController;
use Modules\Core\Http\Controllers\MasterDataController;
use Modules\Core\Http\Controllers\MfaController;
use Modules\Core\Http\Controllers\NotificationController;
use Modules\Core\Http\Controllers\NotificationPreferenceController;
use Modules\Core\Http\Controllers\ProfileController;
use Modules\Core\Http\Controllers\ProviderController;
use Modules\Core\Http\Controllers\SessionController;
use Modules\Core\Http\Controllers\SiteSettingController;

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
|
| Verification-gate policy (docs/decisions/0015-account-verification-gate-policy.md):
| `account.verified` gates everything that consumes resources or produces platform-visible
| state. It never gates what an account needs to repair its identity, secure its credentials, or
| leave — so PUT /profile (identifier correction — ProfileService::updateProfile() already
| re-triggers verification on any change), password/MFA/session management, and account deletion
| all stay reachable to an unverified-but-authenticated user. Gating any of those creates either
| an unrecoverable lockout (a typo'd email that can never receive a code) or actively worsens
| security (refusing to let a possibly-compromised account rotate its password/kill a session).
*/

Route::prefix('v1/core')->name('core.')->group(function () {
    Route::get('/health', [HealthController::class, 'live'])->name('health');
    Route::get('/health/ready', HealthController::class.'@ready')->name('health.ready');

    Route::get('/countries', [MasterDataController::class, 'countries'])->name('countries.index');
    Route::get('/countries/{country}/cities', [MasterDataController::class, 'cities'])->name('countries.cities');
    Route::get('/currencies', [MasterDataController::class, 'currencies'])->name('currencies.index');
    Route::get('/settings', [SiteSettingController::class, 'show'])->name('settings.show');

    // --- Auth: public ---
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:login')->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset')->name('auth.forgot-password');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('auth.reset-password');
    Route::post('/auth/mfa/challenge', [AuthController::class, 'mfaChallenge'])->middleware('throttle:mfa-challenge')->name('auth.mfa.challenge');

    // --- Authenticated (Sanctum) ---
    Route::middleware('auth:sanctum')->group(function () {

        // === Ungated: identity repair, credential/session hygiene, exit — see the policy note
        // above. Never move these behind account.verified. ===
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::post('/auth/verify', [AuthController::class, 'verify'])->middleware('throttle:verification-code-consume')->name('auth.verify');
        Route::post('/auth/resend-code', [AuthController::class, 'resendCode'])->middleware('throttle:verification-code')->name('auth.resend-code');
        Route::put('/auth/password', [AuthController::class, 'changePassword'])->middleware('throttle:password-reset')->name('auth.password.update');
        Route::delete('/auth/account', [AuthController::class, 'destroyAccount'])->middleware('throttle:password-reset')->name('auth.account.destroy');

        Route::get('/auth/sessions', [SessionController::class, 'index'])->name('auth.sessions.index');
        Route::delete('/auth/sessions', [SessionController::class, 'destroyOthers'])->name('auth.sessions.destroy-others');
        Route::delete('/auth/sessions/{session}', [SessionController::class, 'destroy'])->name('auth.sessions.destroy');

        Route::middleware('throttle:mutations')->group(function () {
            Route::get('/auth/mfa', [MfaController::class, 'show'])->name('auth.mfa.show');
            Route::post('/auth/mfa', [MfaController::class, 'enable'])->name('auth.mfa.enable');
            Route::post('/auth/mfa/confirm', [MfaController::class, 'confirm'])->name('auth.mfa.confirm');
            Route::delete('/auth/mfa', [MfaController::class, 'disable'])->name('auth.mfa.disable');
            Route::post('/auth/mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes'])->name('auth.mfa.recovery-codes');
        });

        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->middleware('throttle:mutations')->name('profile.update');

        // === Gated: account.verified — trust-bearing actions and anything beyond bare identity
        // repair. `mutations` is the general per-user write limiter — see docs/api/conventions.md
        // § Rate limiting. ===
        Route::middleware(['account.verified', 'throttle:mutations'])->group(function () {
            Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.store');
            Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
            Route::put('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.language');
            Route::put('/profile/currency', [ProfileController::class, 'updateCurrency'])->name('profile.currency');

            Route::get('/me/capabilities', [CapabilityController::class, 'show'])->name('me.capabilities');

            // FR-PROVIDER-*: activating a provider identity and submitting it for verification are
            // trust-bearing actions — require a verified account first.
            Route::get('/providers/me', [ProviderController::class, 'show'])->name('providers.me');
            Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
            Route::put('/providers/me', [ProviderController::class, 'update'])->name('providers.update');
            Route::post('/providers/me/logo', [ProviderController::class, 'updateLogo'])->name('providers.logo.store');
            Route::delete('/providers/me/logo', [ProviderController::class, 'destroyLogo'])->name('providers.logo.destroy');
            Route::post('/providers/me/cover', [ProviderController::class, 'updateCover'])->name('providers.cover.store');
            Route::delete('/providers/me/cover', [ProviderController::class, 'destroyCover'])->name('providers.cover.destroy');
            Route::post('/providers/me/verification', [ProviderController::class, 'submitVerification'])->name('providers.verification.store');

            Route::get('/me/notification-preferences', [NotificationPreferenceController::class, 'show'])->name('me.notification-preferences.show');
            Route::put('/me/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('me.notification-preferences.update');

            Route::get('/me/notifications', [NotificationController::class, 'index'])->name('me.notifications.index');
            Route::put('/me/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('me.notifications.read');
            Route::post('/me/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('me.notifications.read-all');
        });
    });
});
