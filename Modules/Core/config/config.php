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

    // Saudi Arabia / SAR are THE default country/currency platform-wide — Modules/Core/database/
    // seeders/MasterDataSeeder guarantees these codes always exist. Anywhere a country_id/
    // currency_id relation needs a default (new UserProfile on registration today; anything else
    // added later), look it up by code via this config rather than hardcoding an assumed id — ids
    // are auto-increment and not guaranteed stable across environments, codes are.
    'defaults' => [
        'country_code' => 'SA',
        'currency_code' => 'SAR',
    ],
];
