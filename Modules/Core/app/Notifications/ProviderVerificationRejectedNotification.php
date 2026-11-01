<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * Dispatched from Modules\Core\Services\Admin\ProviderManagementService::reject() — admin-
 * initiated, no explicit $locale at the call site (recipient's own stored preference wins). Not
 * mandatory.
 */
final class ProviderVerificationRejectedNotification extends BaseUserNotification
{
    public int $tries = 3;

    public function __construct(
        private readonly string $reason,
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
        return NotificationEvent::ProviderVerificationRejected;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('core::notifications.provider_verification_rejected.subject', [], $this->renderLocale))
            ->greeting(__('core::notifications.provider_verification_rejected.greeting', [], $this->renderLocale))
            ->line(__('core::notifications.provider_verification_rejected.line_1', [], $this->renderLocale))
            ->line(__('core::notifications.provider_verification_rejected.line_reason', ['reason' => $this->reason], $this->renderLocale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => __('core::notifications.provider_verification_rejected.in_app', [], $this->renderLocale),
            'reason' => $this->reason,
        ];
    }
}
