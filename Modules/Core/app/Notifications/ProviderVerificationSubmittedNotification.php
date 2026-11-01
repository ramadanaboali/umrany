<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * Dispatched from Modules\Core\Services\ProviderService::submitVerification() — self-triggered,
 * so it's sent with an explicit $locale (the current request's own locale), same rule as
 * RegistrationCompletedNotification. Not mandatory — see NotificationEvent::isMandatory().
 */
final class ProviderVerificationSubmittedNotification extends BaseUserNotification
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
        return NotificationEvent::ProviderVerificationSubmitted;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('core::notifications.provider_verification_submitted.subject', [], $this->renderLocale))
            ->greeting(__('core::notifications.provider_verification_submitted.greeting', [], $this->renderLocale))
            ->line(__('core::notifications.provider_verification_submitted.line_1', [], $this->renderLocale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => __('core::notifications.provider_verification_submitted.in_app', [], $this->renderLocale)];
    }
}
