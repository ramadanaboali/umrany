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
    public function index(): View
    {
        $permissions = Permission::query()
            ->where('guard_name', 'admin')
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => Str::before($permission->name, '.'));

        return view('admin.permissions.index', ['permissionGroups' => $permissions]);
    }
}
