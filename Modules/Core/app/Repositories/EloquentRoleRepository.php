<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;

final class EloquentRoleRepository implements RoleRepositoryInterface
{
    private const GUARD = 'admin';

    public function findById(int $id): ?Role
    {
        $role = Role::query()->find($id);

        return $role && $role->guard_name === self::GUARD ? $role : null;
    }

    public function allWithPermissions(): Collection
    {
        return Role::query()->where('guard_name', self::GUARD)->with('permissions')->orderBy('name')->get();
    }

    public function create(string $name, array $permissionNames = []): Role
    {
        // Role::create() is typed to return the Spatie\Permission\Contracts\Role interface
        // (that's what every consumer in this codebase actually wants — the concrete Eloquent
        // model), since Spatie's own package supports swapping the Role model implementation.
        // This app never does; the concrete class is what's genuinely returned.
        /** @var Role $role */
        $role = Role::create(['name' => $name, 'guard_name' => self::GUARD]);
        $role->syncPermissions($permissionNames);

        return $role;
    }

    public function update(Role $role, string $name, array $permissionNames): Role
    {
        $role->update(['name' => $name]);
        $role->syncPermissions($permissionNames);

        return $role;
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function isAssignedToAnyAdmin(Role $role): bool
    {
        return $role->users()->exists();
    }
}
