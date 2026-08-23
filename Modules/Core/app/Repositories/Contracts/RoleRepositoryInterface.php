<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

/**
 * Scoped to the `admin` guard throughout — Roles/Permissions for any other guard are out of
 * scope for this repository (there aren't any others today; see module-boundaries.md).
 */
interface RoleRepositoryInterface
{
    public function findById(int $id): ?Role;

    /**
     * `$search` matches the role name.
     */
    public function paginate(int $perPage = 20, ?string $search = null): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function create(string $name, array $permissionNames = []): Role;

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function update(Role $role, string $name, array $permissionNames): Role;

    public function delete(Role $role): void;

    public function isAssignedToAnyAdmin(Role $role): bool;
}
