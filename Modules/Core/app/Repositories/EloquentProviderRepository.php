<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use Modules\Core\Models\Provider;
use Modules\Core\Repositories\Contracts\ProviderRepositoryInterface;

final class EloquentProviderRepository implements ProviderRepositoryInterface
{
    public function findByUserId(int $userId): ?Provider
    {
        return Provider::query()->where('user_id', $userId)->first();
    }

    public function existsForUser(int $userId): bool
    {
        return Provider::query()->where('user_id', $userId)->exists();
    }

    public function create(array $attributes): Provider
    {
        // forceCreate: `user_id`/`status`/entitlement flags are deliberately excluded from
        // Provider's #[Fillable(...)] — see docs/architecture/backend-layering.md.
        return Provider::forceCreate($attributes);
    }

    public function update(Provider $provider, array $attributes): Provider
    {
        $provider->update($attributes);

        return $provider;
    }
}
