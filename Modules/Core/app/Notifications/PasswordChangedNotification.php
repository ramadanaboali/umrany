<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * Security confirmation sent whenever a password changes (self-service change — a completed
 * reset uses PasswordResetCompletedNotification instead, per the source spec's distinct events).
 * Channel selection (mail/in-app/neither) is entirely preference-driven — see
 * Modules\Core\Notifications\BaseUserNotification — this is a mandatory event
 * (NotificationEvent::isMandatory()), so it always reaches every channel the account has a
 * destination for regardless of stored preference.
 */
final class PasswordChangedNotification extends BaseUserNotification
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
        return NotificationEvent::PasswordChanged;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Umrany password was changed')
            ->greeting('Password changed')
            ->line('This is a confirmation that your account password was just changed.')
            ->line('If you did not make this change, contact support immediately.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => 'Your password was changed.'];
    }
}
