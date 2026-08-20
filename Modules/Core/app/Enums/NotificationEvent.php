<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Every event this platform notifies a user about. See Modules\Core\Notifications\
 * BaseUserNotification, Modules\Core\Services\NotificationPreferenceService, and
 * docs/decisions/0017-notification-preferences-schema.md.
 */
enum NotificationEvent: string
{
    case RegistrationCompleted = 'registration_completed';
    case VerificationCodeSent = 'verification_code';
    case PasswordChanged = 'password_changed';
    case PasswordResetCompleted = 'password_reset_completed';
    case NewDeviceLogin = 'new_device_login';
    case SuspiciousLoginAttempt = 'suspicious_login_attempt';
    case AccountSuspended = 'account_suspended';
    case AccountActivated = 'account_activated';

    /**
     * Both channels on by default for every event — a user opts out, rather than having to opt
     * in to notifications about their own account security.
     *
     * @return array<int, NotificationChannel>
     */
    public function defaultChannels(): array
    {
        return [NotificationChannel::InApp, NotificationChannel::Email];
    }

    /**
     * Security/legal-shaped events a user must not be able to silence entirely — the preferences
     * endpoint accepts but ignores an attempt to disable these, always sending on every channel
     * the account has a destination for.
     */
    public function isMandatory(): bool
    {
        return match ($this) {
            self::VerificationCodeSent,
            self::PasswordChanged,
            self::PasswordResetCompleted,
            self::SuspiciousLoginAttempt,
            self::AccountSuspended => true,
            self::RegistrationCompleted,
            self::NewDeviceLogin,
            self::AccountActivated => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
