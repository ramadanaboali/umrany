<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationEvent;

/**
 * No dispatch site exists yet — there is currently no admin/system action that changes a
 * User::$status after registration (no end-user-management "suspend" screen has been built, see
 * docs/architecture/admin-portal.md "What's still open"). This class exists ready to dispatch the
 * moment such an action lands; building the admin action itself is out of scope here (root
 * CLAUDE.md Rule 0). Mandatory — see NotificationEvent::isMandatory().
 */
final class AccountSuspendedNotification extends BaseUserNotification
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
        return NotificationEvent::AccountSuspended;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Umrany account has been suspended')
            ->greeting('Account suspended')
            ->line('Your account has been suspended.')
            ->line('Contact support if you believe this is a mistake.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['message' => 'Your account has been suspended.'];
    }
}
