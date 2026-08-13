<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use App\Models\User;
use Modules\Core\Models\UserProfile;
use Modules\Core\Repositories\Contracts\UserProfileRepositoryInterface;

final class EloquentUserProfileRepository implements UserProfileRepositoryInterface
{
    public function findByUserId(int $userId): ?UserProfile
    {
        return UserProfile::query()->where('user_id', $userId)->first();
    }

    public function createForUser(User $user, array $attributes): UserProfile
    {
        return $user->profile()->create($attributes);
    }

    public function update(UserProfile $profile, array $attributes): UserProfile
    {
        $profile->update($attributes);

        return $profile;
    }
}
