<?php

return [
    'name' => 'Core',

    // Bootstrap Super Admin — read via config(), not env() directly, everywhere outside this
    // file (larastan.noEnvCallsOutsideOfConfig): env() returns null once config is cached in
    // production, so any read of these values outside a config/*.php file is a latent bug
    // waiting for the first `config:cache` in a real deploy. See
    // Modules/Core/database/seeders/AdminSeeder.
    'bootstrap_admin' => [
        'name' => env('ADMIN_NAME', 'Super Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@umrany.com'),
        'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
    ],

    // Saudi Arabia / SAR are THE default country/currency platform-wide. This is now only the
    // SEED source (MasterDataSeeder sets Country/Currency's is_default flag from these codes) —
    // runtime code calls Country::default()/Currency::default(), which query the DB flag, not
    // this config, so a future admin screen can change the active default without a redeploy.
    // See docs/decisions/0020-database-backed-platform-defaults.md.
    'defaults' => [
        'country_code' => 'SA',
        'currency_code' => 'SAR',
    ],

    // Read by CoreServiceProvider::boot() to build the single Password::defaults() rule every
    // FormRequest that validates a password now shares — see docs/decisions/0013-config-driven-
    // password-policy-and-history.md. history_count = 0 disables password-history checking.
    'password_policy' => [
        'min_length' => (int) env('PASSWORD_MIN_LENGTH', 8),
        'require_mixed_case' => (bool) env('PASSWORD_REQUIRE_MIXED_CASE', true),
        'require_numbers' => (bool) env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => (bool) env('PASSWORD_REQUIRE_SYMBOLS', false),
        'uncompromised' => (bool) env('PASSWORD_CHECK_COMPROMISED', false),
        'history_count' => (int) env('PASSWORD_HISTORY_COUNT', 5),
    ],

    // TOTP MFA — see docs/decisions/0012-totp-mfa-with-two-step-login.md.
    'mfa' => [
        'issuer' => env('MFA_ISSUER', env('APP_NAME', 'Umrany')),
        'window' => (int) env('MFA_WINDOW', 1),
        'recovery_code_count' => (int) env('MFA_RECOVERY_CODE_COUNT', 8),
        'challenge_ttl_seconds' => (int) env('MFA_CHALLENGE_TTL', 300),
        'challenge_max_attempts' => (int) env('MFA_CHALLENGE_MAX_ATTEMPTS', 5),
    ],

    // Avatar upload — read by UpdateAvatarRequest/ProfileService::updateAvatar(). Every uploaded
    // avatar is re-encoded to this format/quality/size regardless of what was uploaded (FR-
    // PROFILE-003 "images shall be automatically optimized").
    'avatar' => [
        'disk' => 'public',
        'path' => 'avatars/users',
        'max_kilobytes' => (int) env('AVATAR_MAX_KILOBYTES', 2048),
        'width' => (int) env('AVATAR_WIDTH', 512),
        'height' => (int) env('AVATAR_HEIGHT', 512),
        'format' => env('AVATAR_FORMAT', 'webp'),
        'quality' => (int) env('AVATAR_QUALITY', 82),
    ],

    'security' => [
        'suspicious_login' => [
            'threshold' => (int) env('SUSPICIOUS_LOGIN_THRESHOLD', 5),
            'window_minutes' => (int) env('SUSPICIOUS_LOGIN_WINDOW_MINUTES', 15),
        ],
    ],
];
