<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

final class RegistrationCompletedNotification extends BaseUserNotification
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
        return NotificationEvent::RegistrationCompleted;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Umrany')
            ->greeting('Welcome to Umrany!')
            ->line('Your account has been created.')
            ->line('Verify your email or mobile number to unlock the rest of the platform.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => 'Welcome to Umrany — your account has been created.'];
    }
}
