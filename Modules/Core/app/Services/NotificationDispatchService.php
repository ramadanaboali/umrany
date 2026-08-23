<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Modules\Core\Enums\NotificationChannel;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Notifications\AccountActivatedNotification;
use Modules\Core\Notifications\AccountSuspendedNotification;
use Modules\Core\Notifications\BaseUserNotification;
use Modules\Core\Notifications\NewDeviceLoginNotification;
use Modules\Core\Notifications\PasswordChangedNotification;
use Modules\Core\Notifications\PasswordResetCompletedNotification;
use Modules\Core\Notifications\RegistrationCompletedNotification;
use Modules\Core\Notifications\SuspiciousLoginAttemptNotification;
use Modules\Core\Notifications\VerificationCodeNotification;

/**
 * The single injectable entry point for every notification matching a NotificationEvent case —
 * see docs/decisions/0029-centralized-notification-service-and-api-locale.md. Callers pass
 * typed $data instead of constructing a notification class directly, so locale resolution and
 * channel-narrowing happen in exactly one place instead of being re-derived at each of the 8
 * call sites.
 *
 * Not used for MfaStateChangedNotification/AdminResetPasswordNotification — neither has a
 * NotificationEvent case, so both stay on their existing direct ->notify() calls.
 */
final class NotificationDispatchService
{
    /**
     * @param  array<string, mixed>  $data  Keyed per event — see build() for exactly what each
     *                                      NotificationEvent case reads out of it.
     * @param  array<int, NotificationChannel>|null  $channels  Narrows (never widens) the
     *                                                          recipient's own channel preferences — see BaseUserNotification::restrictChannelsTo(). Has
     *                                                          no effect on VerificationCodeSent, which picks mail-vs-SMS by delivery type rather than
     *                                                          preference and doesn't consult this restriction at all.
     * @param  string|null  $locale  Explicit override; otherwise the recipient's own stored
     *                               preference, never the current request's locale — the person triggering a notification
     *                               (e.g. an admin suspending a user) is often not its recipient.
     */
    public function send(User $notifiable, NotificationEvent $event, array $data = [], ?array $channels = null, ?string $locale = null): void
    {
        $locale ??= $this->resolveLocale($notifiable);

        $notification = $this->build($event, $data, $locale);

        if ($channels !== null) {
            $notification->restrictChannelsTo($channels);
        }

        $notifiable->notify($notification);
    }

    private function resolveLocale(User $notifiable): string
    {
        return $notifiable->profile?->preferred_language->value ?? config('app.locale');
    }

    private function build(NotificationEvent $event, array $data, string $locale): BaseUserNotification
    {
        return match ($event) {
            NotificationEvent::RegistrationCompleted => new RegistrationCompletedNotification($locale),

            NotificationEvent::VerificationCodeSent => new VerificationCodeNotification(
                $data['plain_code'],
                $data['type'],
                $data['purpose'],
                $data['expires_in_minutes'],
                $locale,
            ),

            NotificationEvent::PasswordChanged => new PasswordChangedNotification($locale),

            NotificationEvent::PasswordResetCompleted => new PasswordResetCompletedNotification($locale),

            NotificationEvent::NewDeviceLogin => new NewDeviceLoginNotification(
                $data['device_name'] ?? null,
                $data['ip'] ?? null,
                $data['logged_in_at'],
                $locale,
            ),

            NotificationEvent::SuspiciousLoginAttempt => new SuspiciousLoginAttemptNotification($data['attempts'], $locale),

            NotificationEvent::AccountSuspended => new AccountSuspendedNotification($data['reason'], $locale),

            NotificationEvent::AccountActivated => new AccountActivatedNotification($locale),
        };
    }
}
