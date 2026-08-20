<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Enums\Language;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active admin's language and applies it for the request — the authenticated
 * admin's own `preferred_language` column wins (so it follows them across devices), falling
 * back to a long-lived `admin_locale` cookie (set by LocaleController — the same "remember this
 * browser" mechanism dark mode uses via localStorage, chosen specifically because it survives
 * the login/logout boundary a session would not) and then English. See
 * docs/decisions/0022-admin-dashboard-en-ar-localization.md.
 *
 * Deliberately does NOT fall back to `config('app.locale')`: `Illuminate\Foundation\Application::
 * setLocale()` (called below) overwrites that very config key as a side effect, so using it as a
 * "default" creates a feedback loop where one request's language choice leaks into the next
 * request's fallback — reproduced directly in this middleware's own test
 * (AdminLocaleTest::test_locale_does_not_leak_across_requests) before this comment was written,
 * not assumed. A hardcoded English default has no such mutation to leak from.
 *
 * `App::setLocale()` is otherwise Octane-safe per request: config/octane.php's default listener
 * set already includes FlushLocaleState/FlushTranslatorCache.
 */
final class SetAdminLocale
{
    /** Must match LocaleController::COOKIE. */
    private const COOKIE = 'admin_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        $language = ($admin ? $admin->preferred_language : null)
            ?? Language::tryFrom((string) $request->cookie(self::COOKIE))
            ?? Language::English;

        App::setLocale($language->value);

        return $next($request);
    }
}
