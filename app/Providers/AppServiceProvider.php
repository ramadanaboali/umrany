<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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
     *
     * Named rate limiters for the identity/auth surfaces — deliberately separate from the
     * generic `throttle:api` limiter already on the `api` middleware group (see
     * docs/api/conventions.md), since credential-guessing and OTP-guessing endpoints need much
     * tighter limits than ordinary read traffic. Applied per-route via `throttle:<name>`.
     */
    public function boot(): void
    {
        // The framework default Authenticate middleware redirects unauthenticated web requests
        // to a route literally named "login" — this app has no such route (the API never
        // redirects, it returns JSON per shouldRenderJsonWhen in bootstrap/app.php, and the
        // only session-based guard is `admin`), so without this it throws RouteNotFoundException
        // instead of redirecting. There is exactly one login screen in the whole app.
        Authenticate::redirectUsing(fn () => route('admin.login'));

        // The default paginator view is Tailwind-flavored; the admin dashboard's Velzon Material
        // theme is Bootstrap 5, so admins/index and users/index would render an unstyled
        // pagination control without this. See docs/decisions/0021-velzon-material-admin-theme.md.
        Paginator::useBootstrapFive();

        // Keeps the /docs URL every doc/skill file already references, rather than Scramble's
        // own default (/docs/api) — see docs/decisions/0023-scramble-over-scribe.md.
        Scramble::configure()->expose(ui: '/docs', document: '/docs/openapi.json');

        // Scramble's RestrictedDocsAccess middleware allows the `local` env unconditionally and
        // otherwise checks this gate — docs stay reachable in every non-production environment
        // (including `testing`, which the API-documentation Pest test needs) without opening them
        // in a real production deploy, should one ever exist. Scribe had no such gate at all.
        Gate::define('viewApiDocs', fn (?object $user = null) => ! app()->isProduction());

        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('login')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('admin-login', function (Request $request) {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('verification-code', function (Request $request) {
            // Keyed on the authenticated user, not email/mobile input — resending a code is an
            // authenticated action (the account already exists), so this can't be used to probe
            // arbitrary addresses the way login/password-reset can.
            $userId = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(1)->by((string) $userId);
        });

        RateLimiter::for('verification-code-consume', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(5)->by((string) $userId);
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // General per-user write limiter for authenticated mutation endpoints that don't have a
        // more specific limiter of their own (profile/provider updates) — looser than the
        // credential/OTP-guessing limiters above, since these aren't guessable/enumerable, just
        // worth bounding against runaway retries or abuse.
        RateLimiter::for('mutations', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(30)->by((string) $userId);
        });

        // MFA login-challenge exchange (public — the request has no authenticated user yet) —
        // keyed on the challenge token itself so guessing codes against one login attempt can't
        // burn through attempts on a different, unrelated one.
        RateLimiter::for('mfa-challenge', function (Request $request) {
            $key = (string) $request->input('challenge_token').'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Public (unauthenticated) account-verification resend/consume — the recovery path for an
        // account that's lost its only session before ever verifying (see docs/decisions/0026-
        // block-login-until-account-verified.md). Same shape as `login`/`password-reset`: keyed
        // on the submitted identifier + IP, since this is reachable with no session at all.
        RateLimiter::for('verification-public', function (Request $request) {
            $key = strtolower((string) $request->input('login')).'|'.$request->ip();

            return Limit::perMinute(3)->by($key);
        });
    }
}
