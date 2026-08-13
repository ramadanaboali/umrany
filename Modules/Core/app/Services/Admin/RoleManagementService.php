<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;

final class RoleManagementService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * @return Collection<int, Role>
     */
    public function all(): Collection
    {
        return $this->roles->allWithPermissions();
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function create(string $name, array $permissionNames): Role
    {
        return $this->roles->create($name, $permissionNames);
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function update(Role $role, string $name, array $permissionNames): Role
    {
        return $this->roles->update($role, $name, $permissionNames);
    }

    public function delete(Role $role): void
    {
        if ($this->roles->isAssignedToAnyAdmin($role)) {
            throw ValidationException::withMessages([
                'role' => ['This role is still assigned to one or more admins — remove it from them first.'],
            ]);
        }

        $this->roles->delete($role);
    }
}
