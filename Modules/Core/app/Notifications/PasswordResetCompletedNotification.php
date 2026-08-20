<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * Sent when the unauthenticated, OTP-based password-reset flow completes — distinct from
 * PasswordChangedNotification (a self-service change while already logged in), per the source
 * spec's separate "Password changed" / "Password reset" events. Mandatory — see
 * NotificationEvent::isMandatory().
 */
final class PasswordResetCompletedNotification extends BaseUserNotification
{
    public int $tries = 3;

    public function __construct()
    {
        $this->onQueue('core-default');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::PasswordResetCompleted;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Umrany password was reset')
            ->greeting('Password reset')
            ->line('Your account password was just reset using a verification code.')
            ->line('If you did not request this, contact support immediately.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => 'Your password was reset.'];
    }
}
