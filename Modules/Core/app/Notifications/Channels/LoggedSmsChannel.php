<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in for a real SMS gateway integration — none is configured yet (see the blank
 * third-party integration keys in .env.example / docs/architecture/tech-stack.md). Logs instead
 * of silently dropping the notification, so mobile-channel verification is still testable/
 * demoable end-to-end in dev/staging. Swap this channel's `send()` body for a real gateway call
 * when one is chosen — nothing else in the notification classes that use it needs to change.
 */
final class LoggedSmsChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toLoggedSms')) {
            return;
        }

        Log::info('[sms:not-configured] '.$notification->toLoggedSms($notifiable), [
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->getKey(),
        ]);
    }
}
