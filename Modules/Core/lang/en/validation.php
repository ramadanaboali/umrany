<?php

declare(strict_types=1);

/**
 * Messages for Modules\Core\Rules\* custom validation rules — shared by both the end-user API
 * (which stays English-only, no locale middleware wraps routes/api.php) and the admin
 * dashboard's own FormRequests (where Modules\App\Http\Middleware\SetAdminLocale makes this
 * actually render in Arabic). Registered under the `core::` namespace — see
 * CoreServiceProvider::registerTranslations().
 */
return [
    'invalid_phone_number' => 'The :attribute must be a valid Saudi or Egyptian mobile number.',
    'password_reused' => 'The :attribute has been used before. Choose a different password.',
];
