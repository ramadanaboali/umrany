<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * Dispatched from Modules\Core\Services\Admin\ProviderManagementService::approve()/reactivate() —
 * admin-initiated, no explicit $locale at either call site (recipient's own stored preference
 * wins). Not mandatory.
 */
final class ProviderProfilePublishedNotification extends BaseUserNotification
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
        return NotificationEvent::ProviderProfilePublished;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('core::notifications.provider_profile_published.subject', [], $this->renderLocale))
            ->greeting(__('core::notifications.provider_profile_published.greeting', [], $this->renderLocale))
            ->line(__('core::notifications.provider_profile_published.line_1', [], $this->renderLocale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => __('core::notifications.provider_profile_published.in_app', [], $this->renderLocale)];
    }
}
