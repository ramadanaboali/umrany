<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Admin dashboard dark/light mode. Persisted per-account (admins.theme_mode) as well as
 * per-browser (localStorage) — see docs/decisions/0021-velzon-material-admin-theme.md's update
 * note on why this changed from browser-only.
 */
enum ThemeMode: string
{
    case Light = 'light';
    case Dark = 'dark';
}
