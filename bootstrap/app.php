<?php

use App\Http\Middleware\SetAdminLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\SetLocaleFromRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Session-based Blade admin dashboard — everything else in this app is API-only.
            // See routes/admin.php and docs/architecture/admin-portal.md. SetAdminLocale runs
            // after 'web' (session already started) and before the route's own auth:admin, so it
            // applies to the login/forgot-password screens too — see
            // docs/decisions/0022-admin-dashboard-en-ar-localization.md.
            Route::middleware(['web', SetAdminLocale::class])->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every module's API routes get this automatically, not just Core's — see
        // Modules\Core\Http\Middleware\SetLocaleFromRequest and docs/decisions/0029-centralized-
        // notification-service-and-api-locale.md.
        $middleware->api(append: [SetLocaleFromRequest::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
