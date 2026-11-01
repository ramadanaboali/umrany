<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Models\Provider;
use Modules\Core\Repositories\Contracts\ProviderRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

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

    public function paginatePublic(int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(
            Provider::query()
                ->where('status', ProviderStatus::Published)
                ->whereHas('verification', fn ($query) => $query->where('status', ProviderVerificationStatus::Approved->value))
        )
            ->allowedFilters(
                AllowedFilter::exact('city_id'),
                AllowedFilter::exact('country_id'),
                AllowedFilter::exact('category', 'categories.id'),
                AllowedFilter::exact('subcategory', 'subcategories.id'),
                AllowedFilter::partial('search', 'company_name'),
            )
            ->with(['verification', 'categories', 'subcategories', 'portfolios', 'certificates'])
            ->latest()
            ->paginate($perPage);
    }
}
