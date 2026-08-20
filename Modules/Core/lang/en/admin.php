<?php

declare(strict_types=1);

/**
 * Admin-visible ValidationException messages thrown by Modules\Core\Services\Admin\* — these
 * render inside the admin dashboard's own (already-translated) screens, so they need to be
 * translated too. Registered under the `core::` namespace — see
 * CoreServiceProvider::registerTranslations() and
 * docs/decisions/0022-admin-dashboard-en-ar-localization.md.
 */
return [
    'cannot_suspend_self' => 'You cannot suspend your own account.',
    'last_active_super_admin' => 'This is the last active Super Admin — promote another admin to Super Admin first.',
    'cannot_delete_self' => 'You cannot delete your own account.',
    'role_still_assigned' => 'This role is still assigned to one or more admins — remove it from them first.',
    'invalid_credentials' => 'These credentials do not match our records.',
    'account_cannot_sign_in' => 'This account cannot sign in. Contact a Super Admin if you believe this is a mistake.',
    'password_previously_used' => 'This password has been used before. Choose a different password.',
];
