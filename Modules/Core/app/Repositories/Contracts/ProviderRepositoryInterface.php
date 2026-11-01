<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Models\Provider;

interface ProviderRepositoryInterface
{
    public function findByUserId(int $userId): ?Provider;

    public function existsForUser(int $userId): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Provider;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Provider $provider, array $attributes): Provider;

    /**
     * Public provider directory — only ever Published providers with an Approved verification.
     * Filters ('filter[city_id]', 'filter[country_id]', 'filter[category]', 'filter[search]')
     * are read from the current request by Spatie\QueryBuilder\QueryBuilder, same convention as
     * Modules\Core\Http\Controllers\NotificationController::index().
     */
    public function paginatePublic(int $perPage = 20): LengthAwarePaginator;
}
