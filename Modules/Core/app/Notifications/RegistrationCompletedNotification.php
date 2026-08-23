<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

final class RegistrationCompletedNotification extends BaseUserNotification
{
    public int $tries = 3;

    public function __construct(
        private readonly string $renderLocale,
    ) {
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
            ->subject(__('core::notifications.registration_completed.subject', [], $this->renderLocale))
            ->greeting(__('core::notifications.registration_completed.greeting', [], $this->renderLocale))
            ->line(__('core::notifications.registration_completed.line_1', [], $this->renderLocale))
            ->line(__('core::notifications.registration_completed.line_2', [], $this->renderLocale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => __('core::notifications.registration_completed.in_app', [], $this->renderLocale)];
    }
}
