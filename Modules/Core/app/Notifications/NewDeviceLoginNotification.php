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
            ->subject('New login to your Umrany account')
            ->greeting('New device login')
            ->line("Your account was just signed in from a device we haven't seen before: {$this->deviceLabel()}.")
            ->line("Time: {$this->loggedInAt->toDayDateTimeString()}")
            ->line('If this was not you, change your password immediately.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => "New login from {$this->deviceLabel()}.",
            'device_name' => $this->deviceName,
            'ip' => $this->ip,
            'logged_in_at' => $this->loggedInAt->toIso8601String(),
        ];
    }

    private function deviceLabel(): string
    {
        return $this->deviceName ?? $this->ip ?? 'an unknown device';
    }
}
