<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security confirmation sent when two-factor authentication is enabled or disabled on an
 * account. Deliberately standalone, not extending BaseUserNotification / gated by
 * NotificationPreference — MFA state changes aren't one of the 8 events the notification-
 * preferences spec names, and mandatory-by-construction (always mail) is the correct behavior
 * for it regardless, so there's nothing a preference toggle would add. Same email-only rationale
 * as PasswordChangedNotification.
 */
final class MfaStateChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly bool $enabled,
    ) {
        // See PasswordChangedNotification's docblock for why this is onQueue(), not a redeclared
        // typed $queue property.
        $this->onQueue('core-default');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = new MailMessage;

        if ($this->enabled) {
            return $message
                ->subject('Two-factor authentication was enabled')
                ->greeting('Two-factor authentication enabled')
                ->line('Two-factor authentication was just turned on for your account.')
                ->line('If you did not make this change, contact support immediately.');
        }

        return $message
            ->subject('Two-factor authentication was disabled')
            ->greeting('Two-factor authentication disabled')
            ->line('Two-factor authentication was just turned off for your account.')
            ->line('If you did not make this change, contact support immediately.');
    }
}
