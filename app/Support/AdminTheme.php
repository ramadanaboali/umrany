<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Enums\Language;
use Modules\Core\Enums\ThemeMode;

/**
 * Single source of truth for the admin dashboard's active language/direction, so the layouts and
 * `partials/head-assets` never compute this independently and drift. See
 * docs/decisions/0022-admin-dashboard-en-ar-localization.md.
 */
final class AdminTheme
{
    public static function language(): Language
    {
        return Language::tryFrom(app()->getLocale()) ?? Language::English;
    }

    public static function direction(): string
    {
        return self::language()->direction();
    }

    public static function isRtl(): bool
    {
        return self::direction() === 'rtl';
    }

    /**
     * The signed-in admin's saved dark/light mode, or null for a guest — the pre-paint boot
     * script (partials/theme-mode-boot.blade.php) falls back to localStorage in that case. Kept
     * separate from language: unlike `dir`, the theme-mode CSS link swap doesn't happen server-
     * side, so this is only ever a *hint* the client-side script may override on first paint if
     * the DB value hasn't caught up with a very recent client-side toggle yet. See
     * docs/decisions/0021-velzon-material-admin-theme.md.
     */
    public static function savedMode(): ?ThemeMode
    {
        return Auth::guard('admin')->user()?->theme_mode;
    }

    /**
     * Resolves a Velzon stylesheet name to its LTR or RTL variant, e.g. `css('app')` ->
     * `vendor/velzon/css/app-rtl.min.css` when the active locale is Arabic.
     */
    public static function css(string $name): string
    {
        $suffix = self::isRtl() ? '-rtl' : '';

        return asset("vendor/velzon/css/{$name}{$suffix}.min.css");
    }
}
