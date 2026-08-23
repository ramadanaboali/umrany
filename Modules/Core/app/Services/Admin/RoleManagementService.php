<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Events\AdminPermissionsChanged;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;

final class RoleManagementService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {}

    public function paginate(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->roles->paginate($perPage, $search);
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
        $role = $this->roles->update($role, $name, $permissionNames);

        // Every admin holding this role may have gained/lost access — tell each of their sessions
        // (if any) to refresh live. See docs/decisions/0008-admin-rbac-live-refresh-via-reverb.md.
        $role->users()->pluck('id')->each(
            fn (int $adminId) => AdminPermissionsChanged::dispatch($adminId)
        );

        return $role;
    }

    public function delete(Role $role): void
    {
        if ($this->roles->isAssignedToAnyAdmin($role)) {
            throw ValidationException::withMessages([
                'role' => [__('core::admin.role_still_assigned')],
            ]);
        }

        $this->roles->delete($role);
    }
}
