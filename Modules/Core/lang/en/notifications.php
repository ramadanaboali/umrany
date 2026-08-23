<?php

declare(strict_types=1);

/**
 * One key per Modules\Core\Enums\NotificationEvent case — see Modules\Core\Services\
 * NotificationDispatchService and docs/decisions/0029-centralized-notification-service-and-api-
 * locale.md. Every notification class renders through __('core::notifications.<event>.<key>',
 * [...], $this->locale) — the locale is captured at dispatch time (the recipient's own stored
 * preference by default), never read from mutable global app state inside a queue worker.
 */
return [
    'registration_completed' => [
        'subject' => 'Welcome to Umrany',
        'greeting' => 'Welcome to Umrany!',
        'line_1' => 'Your account has been created.',
        'line_2' => 'Verify your email or mobile number to unlock the rest of the platform.',
        'in_app' => 'Welcome to Umrany — your account has been created.',
    ],

    'verification_code' => [
        'verify' => [
            'subject' => 'Your Umrany verification code',
            'greeting' => 'Verify your account',
        ],
        'reset' => [
            'subject' => 'Your Umrany password reset code',
            'greeting' => 'Reset your password',
        ],
        'line_code' => 'Your code is: :code',
        'line_expires' => 'This code expires in :minutes minutes and can only be used once.',
        'line_ignore' => 'If you did not request this, you can safely ignore this message.',
        'in_app' => 'A verification code was sent.',
        'sms_verify' => 'Umrany verification code: :code (expires in :minutes min)',
        'sms_reset' => 'Umrany password reset code: :code (expires in :minutes min)',
    ],

    'password_changed' => [
        'subject' => 'Your Umrany password was changed',
        'greeting' => 'Password changed',
        'line_1' => 'This is a confirmation that your account password was just changed.',
        'line_2' => 'If you did not make this change, contact support immediately.',
        'in_app' => 'Your password was changed.',
    ],

    'password_reset_completed' => [
        'subject' => 'Your Umrany password was reset',
        'greeting' => 'Password reset',
        'line_1' => 'Your account password was just reset using a verification code.',
        'line_2' => 'If you did not request this, contact support immediately.',
        'in_app' => 'Your password was reset.',
    ],

    'new_device_login' => [
        'subject' => 'New login to your Umrany account',
        'greeting' => 'New device login',
        'line_1' => "Your account was just signed in from a device we haven't seen before: :device.",
        'line_2' => 'Time: :time',
        'line_3' => 'If this was not you, change your password immediately.',
        'in_app' => 'New login from :device.',
        'unknown_device' => 'an unknown device',
    ],

    'suspicious_login_attempt' => [
        'subject' => 'Suspicious sign-in activity on your Umrany account',
        'greeting' => 'Multiple failed sign-in attempts',
        'line_1' => 'There have been :attempts failed sign-in attempts on your account recently.',
        'line_2' => 'If this was not you, consider changing your password.',
        'in_app' => 'There have been :attempts failed sign-in attempts on your account.',
    ],

    'account_suspended' => [
        'subject' => 'Your Umrany account has been suspended',
        'greeting' => 'Account suspended',
        'line_1' => 'Your account has been suspended.',
        'line_reason' => 'Reason: :reason',
        'line_3' => 'Contact support if you believe this is a mistake.',
        'in_app' => 'Your account has been suspended.',
    ],

    'account_activated' => [
        'subject' => 'Your Umrany account has been reactivated',
        'greeting' => 'Account reactivated',
        'line_1' => 'Your account has been reactivated and you can sign in again.',
        'in_app' => 'Your account has been reactivated.',
    ],
];
