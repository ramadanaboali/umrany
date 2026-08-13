<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    /**
     * Resolves either identifier the platform accepts at login/reset (FR-AUTH-001/002).
     */
    public function findByLoginIdentifier(string $login): ?User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function forceUpdate(User $user, array $attributes): User;
}
