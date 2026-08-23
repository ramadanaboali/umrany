<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

/**
 * Read-only — the permission catalog itself is code-defined (Modules/Core/database/seeders/
 * PermissionSeeder), not admin-editable. What admins manage is which roles have which
 * permissions (RoleController) and which admins have which roles (AdminController). Deliberately
 * does not surface which roles hold which permission here — that's `RoleController`'s concern;
 * this screen is just the catalog's own shape.
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

    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $permissions = Permission::query()
            ->where('guard_name', 'admin')
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

        $search = $request->string('search')->value() ?: null;

        // Paginated by resource row, not by permission — the matrix is small today but every
        // future business module (Projects/ECommerce/ERP/AI) adds its own resource rows here over
        // time, so this isn't speculative. In-memory pagination is fine at this scale; there's no
        // query to paginate, the catalog itself is code-defined and already fully loaded above.
        $filtered = $search
            ? $matrix->filter(fn ($resourcePermissions, $resource) => str_contains(Str::lower($resource), Str::lower($search))
                || str_contains(Str::lower(__('admin.permissions.resources.'.$resource)), Str::lower($search)))
            : $matrix;

        $page = (int) $request->integer('page', 1);
        $paged = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.permissions.index', [
            'actions' => $actions,
            'matrix' => $paged,
            'search' => $search,
        ]);
    }
}
