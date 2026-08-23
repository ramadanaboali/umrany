<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * Dispatched from Modules\Core\Services\Admin\UserManagementService::reactivate() — the
 * un-suspend counterpart to AccountSuspendedNotification, see docs/decisions/0027-admin-user-
 * suspend-reactivate.md. Not mandatory — see NotificationEvent::isMandatory().
 */
final class AccountActivatedNotification extends BaseUserNotification
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
        return NotificationEvent::AccountActivated;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('core::notifications.account_activated.subject', [], $this->renderLocale))
            ->greeting(__('core::notifications.account_activated.greeting', [], $this->renderLocale))
            ->line(__('core::notifications.account_activated.line_1', [], $this->renderLocale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => __('core::notifications.account_activated.in_app', [], $this->renderLocale)];
    }
}
