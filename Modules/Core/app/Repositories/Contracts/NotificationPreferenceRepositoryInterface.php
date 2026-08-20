<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Models\NotificationPreference;

interface NotificationPreferenceRepositoryInterface
{
    /**
     * @return Collection<int, NotificationPreference>
     */
    public function allForUser(User $user): Collection;

    public function findForUserAndEvent(User $user, NotificationEvent $event): ?NotificationPreference;

    /**
     * @param  array<int, array{event_type: NotificationEvent, in_app: bool, email: bool}>  $rows
     */
    public function upsertForUser(User $user, array $rows): void;
}
