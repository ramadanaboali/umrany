<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

/**
 * Read-only — the permission catalog itself is code-defined (Modules/Core/database/seeders/
 * PermissionSeeder), not admin-editable. What admins manage is which roles have which
 * permissions (RoleController) and which admins have which roles (AdminController).
 */
final class PermissionController extends Controller
{
    /**
     * Fixed display order for the resource x action matrix below — not every resource
     * necessarily has all five (a future resource might only need `list`/`view`), so the view
     * only renders a column if at least one resource in the catalog actually has it.
     *
     * @var list<string>
     */
    private const ACTION_ORDER = ['list', 'view', 'create', 'update', 'delete'];

    public function index(): View
    {
        $permissions = Permission::query()
            ->where('guard_name', 'admin')
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => Str::before($permission->name, '.'));

        // [resource => [action => Permission]] — a compact matrix instead of a flat per-permission
        // list, so the page stays scannable as the catalog grows (see docs/architecture/
        // admin-portal.md § Permissions).
        $matrix = $permissions->map(
            fn ($resourcePermissions) => $resourcePermissions
                ->keyBy(fn (Permission $permission) => Str::after($permission->name, '.'))
                ->sortBy(fn ($permission, $action) => array_search($action, self::ACTION_ORDER, true))
        );

        $actions = array_values(array_intersect(
            self::ACTION_ORDER,
            $matrix->flatMap(fn ($resourcePermissions) => $resourcePermissions->keys())->unique()->all(),
        ));

        return view('admin.permissions.index', [
            'actions' => $actions,
            'matrix' => $matrix,
        ]);
    }
}
