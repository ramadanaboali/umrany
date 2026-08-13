<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use App\Models\User;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByLoginIdentifier(string $login): ?User
    {
        return User::query()
            ->where('email', $login)
            ->orWhere('mobile', $login)
            ->first();
    }

    public function create(array $attributes): User
    {
        // forceCreate: `status`/`terms_accepted_at` are deliberately excluded from User's
        // #[Fillable(...)] — see docs/architecture/backend-layering.md and Modules/Core/CLAUDE.md
        // "Gotchas" for why this must be forceCreate, not create().
        return User::forceCreate($attributes);
    }

    public function forceUpdate(User $user, array $attributes): User
    {
        $user->forceFill($attributes)->save();

        return $user;
    }
}
