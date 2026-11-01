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

    'provider_verification_submitted' => [
        'subject' => 'Your Umrany provider verification was submitted',
        'greeting' => 'Verification submitted',
        'line_1' => 'Your provider verification documents were submitted and are now pending admin review.',
        'in_app' => 'Your provider verification was submitted for review.',
    ],

    'provider_verification_approved' => [
        'subject' => 'Your Umrany provider verification was approved',
        'greeting' => 'Verification approved',
        'line_1' => 'Your provider verification has been approved.',
        'in_app' => 'Your provider verification was approved.',
    ],

    'provider_verification_rejected' => [
        'subject' => 'Your Umrany provider verification was rejected',
        'greeting' => 'Verification rejected',
        'line_1' => 'Your provider verification has been rejected.',
        'line_reason' => 'Reason: :reason',
        'in_app' => 'Your provider verification was rejected.',
    ],

    'provider_profile_published' => [
        'subject' => 'Your Umrany provider profile is now live',
        'greeting' => 'Provider profile published',
        'line_1' => 'Your provider profile is now published and visible to project owners on Umrany.',
        'in_app' => 'Your provider profile is now published.',
    ],

    'provider_profile_suspended' => [
        'subject' => 'Your Umrany provider profile has been suspended',
        'greeting' => 'Provider profile suspended',
        'line_1' => 'Your provider profile has been suspended.',
        'line_reason' => 'Reason: :reason',
        'in_app' => 'Your provider profile was suspended.',
    ],

    'government_verification_completed' => [
        'subject_succeeded' => 'Your government verification check is complete',
        'subject_failed' => 'Your government verification check could not be completed',
        'greeting' => 'Government verification',
        'line_succeeded' => 'Your commercial registration number was successfully checked against the government registry. An administrator will review the imported information before it is applied to your profile.',
        'line_failed' => 'We were unable to complete your government verification check.',
        'line_reason' => 'Reason: :reason',
        'in_app_succeeded' => 'Your government verification check completed successfully.',
        'in_app_failed' => 'Your government verification check failed.',
    ],
];
