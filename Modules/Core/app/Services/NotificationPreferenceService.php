<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Modules\Core\Enums\NotificationChannel;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Models\NotificationPreference;
use Modules\Core\Repositories\Contracts\NotificationPreferenceRepositoryInterface;

/**
 * The single question every Notification's via() asks (allows()), plus the GET/PUT matrix
 * endpoint's backing logic. See docs/decisions/0017-notification-preferences-schema.md.
 */
final class NotificationPreferenceService
{
    public function __construct(
        private readonly NotificationPreferenceRepositoryInterface $preferences,
    ) {}

    /**
     * The full matrix for this user — every event, merging stored rows over
     * NotificationEvent::defaultChannels() so a user with zero rows still gets a complete,
     * correct matrix. No backfill migration needed, no row written until they change something.
     *
     * @return array<int, array{event_type: string, mandatory: bool, in_app: bool, email: bool}>
     */
    public function matrixFor(User $user): array
    {
        $stored = $this->preferences->allForUser($user)->keyBy(fn (NotificationPreference $p) => $p->event_type->value);

        return array_map(function (NotificationEvent $event) use ($stored) {
            /** @var NotificationPreference|null $row */
            $row = $stored->get($event->value);
            $defaults = $event->defaultChannels();

            return [
                'event_type' => $event->value,
                'mandatory' => $event->isMandatory(),
                // Larastan wrongly infers $row as never-null here (same false-positive pattern as
                // CapabilityService's $provider/$user nullsafes) — $stored->get() genuinely
                // returns null for any event the user has no stored row for. Keep the nullsafe.
                // @phpstan-ignore nullsafe.neverNull
                'in_app' => $row?->in_app ?? in_array(NotificationChannel::InApp, $defaults, true),
                // @phpstan-ignore nullsafe.neverNull
                'email' => $row?->email ?? in_array(NotificationChannel::Email, $defaults, true),
            ];
        }, NotificationEvent::cases());
    }

    /**
     * @param  array<int, array{event_type: string, in_app: bool, email: bool}>  $requested
     * @return array<int, array{event_type: string, mandatory: bool, in_app: bool, email: bool}>
     */
    public function update(User $user, array $requested): array
    {
        $rows = [];

        foreach ($requested as $entry) {
            $event = NotificationEvent::from($entry['event_type']);

            // Mandatory events can't be disabled — silently force both channels on rather than
            // rejecting the whole request over one ignored field.
            $rows[] = [
                'event_type' => $event,
                'in_app' => $event->isMandatory() ? true : $entry['in_app'],
                'email' => $event->isMandatory() ? true : $entry['email'],
            ];
        }

        $this->preferences->upsertForUser($user, $rows);

        return $this->matrixFor($user);
    }

    public function allows(User $user, NotificationEvent $event, NotificationChannel $channel): bool
    {
        if ($event->isMandatory()) {
            return true;
        }

        $row = $this->preferences->findForUserAndEvent($user, $event);

        if ($row === null) {
            return in_array($channel, $event->defaultChannels(), true);
        }

        return match ($channel) {
            NotificationChannel::InApp => $row->in_app,
            NotificationChannel::Email => $row->email,
        };
    }
}
