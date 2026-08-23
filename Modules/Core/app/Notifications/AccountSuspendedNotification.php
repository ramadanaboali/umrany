<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * Dispatched from Modules\Core\Services\Admin\UserManagementService::suspend() — see
 * docs/decisions/0027-admin-user-suspend-reactivate.md. Mandatory — see
 * NotificationEvent::isMandatory().
 */
final class AccountSuspendedNotification extends BaseUserNotification
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
        return NotificationEvent::AccountSuspended;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('core::notifications.account_suspended.subject', [], $this->renderLocale))
            ->greeting(__('core::notifications.account_suspended.greeting', [], $this->renderLocale))
            ->line(__('core::notifications.account_suspended.line_1', [], $this->renderLocale))
            ->line(__('core::notifications.account_suspended.line_reason', ['reason' => $this->reason], $this->renderLocale))
            ->line(__('core::notifications.account_suspended.line_3', [], $this->renderLocale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => __('core::notifications.account_suspended.in_app', [], $this->renderLocale),
            'reason' => $this->reason,
        ];
    }
}
