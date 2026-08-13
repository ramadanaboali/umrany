<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use App\Models\User;
use Modules\Core\Models\UserProfile;

interface UserProfileRepositoryInterface
{
    public function findByUserId(int $userId): ?UserProfile;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForUser(User $user, array $attributes): UserProfile;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(UserProfile $profile, array $attributes): UserProfile;
}
