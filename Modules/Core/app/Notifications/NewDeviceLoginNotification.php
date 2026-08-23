<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Modules\Core\Enums\NotificationEvent;

final class NewDeviceLoginNotification extends BaseUserNotification
{
    public int $tries = 3;

    public function __construct(
        private readonly ?string $deviceName,
        private readonly ?string $ip,
        private readonly Carbon $loggedInAt,
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
        return NotificationEvent::NewDeviceLogin;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('core::notifications.new_device_login.subject', [], $this->renderLocale))
            ->greeting(__('core::notifications.new_device_login.greeting', [], $this->renderLocale))
            ->line(__('core::notifications.new_device_login.line_1', ['device' => $this->deviceLabel()], $this->renderLocale))
            ->line(__('core::notifications.new_device_login.line_2', ['time' => $this->loggedInAt->toDayDateTimeString()], $this->renderLocale))
            ->line(__('core::notifications.new_device_login.line_3', [], $this->renderLocale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => __('core::notifications.new_device_login.in_app', ['device' => $this->deviceLabel()], $this->renderLocale),
            'device_name' => $this->deviceName,
            'ip' => $this->ip,
            'logged_in_at' => $this->loggedInAt->toIso8601String(),
        ];
    }

    private function deviceLabel(): string
    {
        return $this->deviceName ?? $this->ip ?? __('core::notifications.new_device_login.unknown_device', [], $this->renderLocale);
    }
}
