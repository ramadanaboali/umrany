<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Database\Seeders\PermissionSeeder;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Core\Models\Admin;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Two modes:
 *
 * 1. No options — a full rebuild of the admin-guard RBAC tables (`permissions`, `roles`,
 *    `role_has_permissions`, `model_has_roles`, `model_has_permissions`), not just an idempotent
 *    reseed. Roles/permissions aren't only the two code-defined examples in
 *    `Modules/Core/config/permissions.php` — an admin can create additional roles through the
 *    dashboard (`RoleManagementService`), and those live in the very same tables. This command
 *    therefore snapshots EVERY existing role (its full permission set, code-defined or
 *    dashboard-created) and every admin's role assignments before rebuilding, then restores all
 *    of it after — a plain truncate-and-reseed would otherwise silently destroy any
 *    dashboard-created role and drop every admin assigned to one.
 * 2. `--attach`/`--detach` with `--role` — an ad-hoc, non-destructive permission ↔ role edit on
 *    one existing role.
 *
 * Postgres has no `SET FOREIGN_KEY_CHECKS=0` (that's a MySQL-ism) — a single multi-table
 * `TRUNCATE ... CASCADE` is the correct equivalent: Postgres truncates the pivot tables (children)
 * and roles/permissions (parents) together in one statement, so FK ordering never matters and
 * there is nothing to "disable" first.
 *
 * Both modes explicitly forget Spatie's permission cache afterward. `syncPermissions()`/
 * `givePermissionTo()` already do this internally (verified in
 * vendor/spatie/laravel-permission/src/Traits/HasPermissions.php), but this command calls it
 * again itself so cache invalidation is never silently dependent on that implementation detail —
 * see docs/modules/core.md § Admin RBAC.
 */
final class SyncPermissionsCommand extends Command
{
    protected $signature = 'core:sync-permissions
        {--attach= : Permission name to attach to --role}
        {--detach= : Permission name to detach from --role}
        {--role= : Role name the --attach/--detach option applies to}';

    protected $description = 'Rebuild the admin permission/role tables from scratch (preserving dashboard-created roles and admin assignments); or attach/detach a single permission on a role.';

    public function handle(): int
    {
        $attach = $this->option('attach');
        $detach = $this->option('detach');
        $role = $this->option('role');

        if ($attach !== null || $detach !== null) {
            return $this->attachOrDetach($attach, $detach, $role);
        }

        return $this->rebuildAll();
    }

    private function rebuildAll(): int
    {
        // This whole domain is admin-guard-only (Modules\Core\Models\Admin is "the only model in
        // the application using Spatie's HasRoles trait" — see its docblock). Refuse to run the
        // destructive truncate if that invariant ever stops holding, rather than silently wiping
        // another guard's roles/permissions too.
        if (Role::where('guard_name', '!=', 'admin')->exists() || Permission::where('guard_name', '!=', 'admin')->exists()) {
            $this->error('Refusing to rebuild: found roles/permissions on a guard other than "admin". This command assumes admin is the only guard using Spatie roles/permissions — update it before running.');

            return self::FAILURE;
        }

        $roleSnapshots = Role::where('guard_name', 'admin')->with('permissions')->get()
            ->map(fn (Role $role) => ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()])
            ->all();

        $adminAssignments = Admin::query()->with('roles')->get()
            ->mapWithKeys(fn (Admin $admin) => [$admin->id => $admin->roles->pluck('name')->all()])
            ->filter(fn (array $roleNames) => $roleNames !== [])
            ->all();

        DB::transaction(function () use ($roleSnapshots, $adminAssignments) {
            $this->truncateRbacTables();

            $this->call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
            $this->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);

            // Recreate every previously-existing role (code-defined ones are already back via
            // the seeders above; this line is what brings dashboard-created roles back) and
            // re-sync its exact permission set.
            foreach ($roleSnapshots as $snapshot) {
                foreach ($snapshot['permissions'] as $permissionName) {
                    Permission::findOrCreate($permissionName, 'admin');
                }

                Role::findOrCreate($snapshot['name'], 'admin')->syncPermissions($snapshot['permissions']);
            }

            foreach ($adminAssignments as $adminId => $roleNames) {
                Admin::find($adminId)?->syncRoles($roleNames);
            }
        });

        $this->forgetCache();

        $totalRoles = Role::where('guard_name', 'admin')->count();
        $totalPermissions = Permission::where('guard_name', 'admin')->count();
        $this->info("Rebuilt {$totalRoles} role(s) and {$totalPermissions} permission(s). ".count($adminAssignments).' admin(s) had their role assignments restored.');

        return self::SUCCESS;
    }

    /**
     * Postgres (production) gets a fast native multi-table TRUNCATE with CASCADE — the correct
     * equivalent of MySQL's SET FOREIGN_KEY_CHECKS=0 dance. Every other driver (the test suite
     * runs on an isolated sqlite :memory:, see phpunit.xml) has no TRUNCATE statement worth
     * relying on, so those get a plain DELETE in child-before-parent order instead — same end
     * state, just not a single atomic statement.
     */
    private function truncateRbacTables(): void
    {
        $tables = ['model_has_permissions', 'model_has_roles', 'role_has_permissions', 'roles', 'permissions'];

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('TRUNCATE TABLE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');

            return;
        }

        foreach ($tables as $table) {
            DB::table($table)->delete();
        }
    }

    private function attachOrDetach(?string $attach, ?string $detach, ?string $role): int
    {
        if ($attach !== null && $detach !== null) {
            $this->error('Pass either --attach or --detach, not both.');

            return self::FAILURE;
        }

        if ($role === null) {
            $this->error('--role is required with --attach/--detach.');

            return self::FAILURE;
        }

        try {
            $roleModel = Role::findByName($role, 'admin');
        } catch (RoleDoesNotExist) {
            $this->error("Role \"{$role}\" does not exist.");

            return self::FAILURE;
        }

        $permissionName = $attach ?? $detach;
        $permission = Permission::findOrCreate($permissionName, 'admin');

        if ($attach !== null) {
            $roleModel->givePermissionTo($permission);
            $this->info("Attached \"{$permissionName}\" to role \"{$role}\".");
        } else {
            $roleModel->revokePermissionTo($permission);
            $this->info("Detached \"{$permissionName}\" from role \"{$role}\".");
        }

        $this->forgetCache();

        $affected = $roleModel->users()->count();
        $this->info("{$affected} admin(s) currently hold \"{$role}\" and see this change on their next request.");

        return self::SUCCESS;
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
