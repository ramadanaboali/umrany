<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Models\NotificationPreference;
use Modules\Core\Repositories\Contracts\NotificationPreferenceRepositoryInterface;

final class EloquentNotificationPreferenceRepository implements NotificationPreferenceRepositoryInterface
{
    public function allForUser(User $user): Collection
    {
        return NotificationPreference::query()->where('user_id', $user->id)->get();
    }

    public function findForUserAndEvent(User $user, NotificationEvent $event): ?NotificationPreference
    {
        return NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event_type', $event->value)
            ->first();
    }

    public function upsertForUser(User $user, array $rows): void
    {
        foreach ($rows as $row) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'event_type' => $row['event_type']->value],
                ['in_app' => $row['in_app'], 'email' => $row['email']],
            );
        }
    }
}
