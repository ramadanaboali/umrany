<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Modules\Core\Enums\Language;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-user API locale detection — the `Accept-Language` header if the client sent one, otherwise
 * the authenticated user's own stored `UserProfile::preferred_language`, otherwise the app
 * default. Deliberately separate from `App\Http\Middleware\SetAdminLocale` (session/`web`-guard
 * only, its own resolution order) — see docs/decisions/0029-centralized-notification-service-and-
 * api-locale.md. Registered globally on the `api` middleware group in `bootstrap/app.php`, not
 * per-route.
 *
 * Deliberately NOT `$request->getPreferredLanguage(Language::values())` — that method falls back
 * to the *first* element of the given locale list whenever nothing matches (including when no
 * header was sent at all, confirmed directly: it never returns null for a non-empty list),
 * silently short-circuiting the stored-preference fallback below. `getLanguages()` still does the
 * real quality-value parsing (no hand-rolled Accept-Language parsing here) — only the "nothing
 * matched" decision is ours to make, not the header's own parser's.
 */
final class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = Language::values();

        $locale = collect($request->getLanguages())
            ->map(fn (string $language) => Str::before($language, '_'))
            ->first(fn (string $language) => in_array($language, $supported, true));

        $locale ??= $request->user()?->profile?->preferred_language->value;
        $locale ??= config('app.locale');

        App::setLocale($locale);

        return $next($request);
    }
}
