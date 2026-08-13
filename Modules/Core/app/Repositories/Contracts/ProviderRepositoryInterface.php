<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

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
}
