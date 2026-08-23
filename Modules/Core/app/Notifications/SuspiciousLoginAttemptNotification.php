<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * Fired once per config('core.security.suspicious_login.window_minutes') window after
 * config('core.security.suspicious_login.threshold') consecutive failed login attempts against a
 * real account — see Modules\Core\Services\AuthService::login(). Never fired for an identifier
 * that doesn't match any account, so this can't be used to enumerate registered emails/mobiles.
 * Mandatory — see NotificationEvent::isMandatory().
 */
final class SuspiciousLoginAttemptNotification extends BaseUserNotification
{
    public int $tries = 3;

    public function __construct(
        private readonly int $attempts,
        private readonly string $renderLocale,
    ) {
        $this->onQueue('core-high');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::SuspiciousLoginAttempt;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('core::notifications.suspicious_login_attempt.subject', [], $this->renderLocale))
            ->greeting(__('core::notifications.suspicious_login_attempt.greeting', [], $this->renderLocale))
            ->line(__('core::notifications.suspicious_login_attempt.line_1', ['attempts' => $this->attempts], $this->renderLocale))
            ->line(__('core::notifications.suspicious_login_attempt.line_2', [], $this->renderLocale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => __('core::notifications.suspicious_login_attempt.in_app', ['attempts' => $this->attempts], $this->renderLocale),
            'attempts' => $this->attempts,
        ];
    }
}
