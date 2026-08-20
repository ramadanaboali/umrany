<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Core\Services\Admin\RoleManagementService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleManagementService $roles,
    ) {}

    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => $this->roles->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->roles->create($request->string('name')->value(), $request->input('permissions', []));

        return redirect()->route('admin.roles.index')->with('status', __('admin.flash.role_created'));
    }

    public function edit(Role $role): View
    {
        abort_if($role->guard_name !== 'admin', 404);

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->guard_name !== 'admin', 404);

        $this->roles->update($role, $request->string('name')->value(), $request->input('permissions', []));

        return redirect()->route('admin.roles.index')->with('status', __('admin.flash.role_updated'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->guard_name !== 'admin', 404);

        $this->roles->delete($role);

        return redirect()->route('admin.roles.index')->with('status', __('admin.flash.role_deleted'));
    }

    /**
     * The outer collection is intentionally typed as the base Support\Collection, not
     * Eloquent\Collection — Eloquent\Collection's own generic template requires its values to be
     * Model instances, which a group-of-models (itself a Collection) is not. Kept in the
     * controller (not a Service) — this is presentation grouping for the checkbox UI, not a
     * business rule.
     *
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Permission>>
     */
    private function groupedPermissions(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => Str::before($permission->name, '.'));
    }
}
