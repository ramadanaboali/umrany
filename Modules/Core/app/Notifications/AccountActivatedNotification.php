<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * No dispatch site exists yet — see AccountSuspendedNotification's docblock; the same "no admin
 * action mutates status yet" gap applies here (this is the un-suspend counterpart). Not
 * mandatory — see NotificationEvent::isMandatory().
 */
final class AccountActivatedNotification extends BaseUserNotification
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
        return NotificationEvent::AccountActivated;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Umrany account has been reactivated')
            ->greeting('Account reactivated')
            ->line('Your account has been reactivated and you can sign in again.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => 'Your account has been reactivated.'];
    }
}
