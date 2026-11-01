<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * Dispatched from Modules\Core\Services\GovernmentVerificationService::check() — self-triggered,
 * explicit $locale at the call site (same rule as ProviderVerificationSubmittedNotification). Not
 * mandatory. Sent whether the check succeeded or failed — the provider needs to know either way.
 */
final class GovernmentVerificationCompletedNotification extends BaseUserNotification
{
    public int $tries = 3;

    public function __construct(
        private readonly bool $succeeded,
        private readonly ?string $failureReason,
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
        return NotificationEvent::GovernmentVerificationCompleted;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $key = $this->succeeded ? 'succeeded' : 'failed';

        $mail = (new MailMessage)
            ->subject(__("core::notifications.government_verification_completed.subject_{$key}", [], $this->renderLocale))
            ->greeting(__('core::notifications.government_verification_completed.greeting', [], $this->renderLocale))
            ->line(__("core::notifications.government_verification_completed.line_{$key}", [], $this->renderLocale));

        if (! $this->succeeded && $this->failureReason !== null) {
            $mail->line(__('core::notifications.government_verification_completed.line_reason', ['reason' => $this->failureReason], $this->renderLocale));
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $key = $this->succeeded ? 'succeeded' : 'failed';

        return [
            'message' => __("core::notifications.government_verification_completed.in_app_{$key}", [], $this->renderLocale),
            'succeeded' => $this->succeeded,
            'failure_reason' => $this->failureReason,
        ];
    }
}
