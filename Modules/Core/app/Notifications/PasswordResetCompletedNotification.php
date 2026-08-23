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
        return NotificationEvent::PasswordResetCompleted;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('core::notifications.password_reset_completed.subject', [], $this->renderLocale))
            ->greeting(__('core::notifications.password_reset_completed.greeting', [], $this->renderLocale))
            ->line(__('core::notifications.password_reset_completed.line_1', [], $this->renderLocale))
            ->line(__('core::notifications.password_reset_completed.line_2', [], $this->renderLocale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => __('core::notifications.password_reset_completed.in_app', [], $this->renderLocale)];
    }
}
