<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Core\Enums\NotificationChannel;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Services\NotificationPreferenceService;

/**
 * Shared via() logic for every notification that respects NotificationPreference — checks the
 * user's preference for (this event, in-app) and (this event, email), and never sends mail to an
 * account with no email on file. Mandatory events (NotificationEvent::isMandatory()) always send
 * on every channel the account has a destination for, regardless of stored preference. See
 * docs/decisions/0017-notification-preferences-schema.md.
 *
 * Concrete classes implement event() and toArray() (the in-app payload); toMail() is optional —
 * a class with no toMail() simply never requests the 'mail' channel in practice, since via()
 * still includes it but Laravel's mail channel would then have nothing to send... concrete
 * classes that support email MUST implement toMail() too. (Every one in this codebase does.)
 */
abstract class BaseUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<int, NotificationChannel>|null
     */
    private ?array $channelRestriction = null;

    abstract public function event(): NotificationEvent;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(mixed $notifiable): array;

    /**
     * Narrows (never widens) the channels via() will return — set by
     * NotificationDispatchService when a caller passes an explicit $channels list. Intersected
     * with, not exempt from, the preference-computed set below: a caller can say "only email for
     * this one," never force a channel the recipient explicitly opted out of for a non-mandatory
     * event. See docs/decisions/0029-centralized-notification-service-and-api-locale.md.
     *
     * @param  array<int, NotificationChannel>  $channels
     */
    public function restrictChannelsTo(array $channels): static
    {
        $this->channelRestriction = $channels;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $preferences = app(NotificationPreferenceService::class);
        $channels = [];

        if ($this->channelAllowed(NotificationChannel::InApp)
            && $preferences->allows($notifiable, $this->event(), NotificationChannel::InApp)) {
            $channels[] = 'database';
        }

        if ($notifiable->email !== null
            && $this->channelAllowed(NotificationChannel::Email)
            && $preferences->allows($notifiable, $this->event(), NotificationChannel::Email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    private function channelAllowed(NotificationChannel $channel): bool
    {
        return $this->channelRestriction === null || in_array($channel, $this->channelRestriction, true);
    }
}
