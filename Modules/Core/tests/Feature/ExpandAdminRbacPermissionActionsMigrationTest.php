<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The test suite's base migration run already includes
 * 2026_08_16_000000_expand_admin_rbac_permission_actions, so by the time any test starts the old
 * coarse permissions are already gone. These tests recreate the "before" state (the old coarse
 * permissions, granted to a role) and invoke the migration's up() directly to prove the remap
 * logic itself is correct — not via artisan migrate:rollback, since this migration's down() is
 * intentionally a no-op (see its docblock) and so cannot undo anything to roll back to.
 * See docs/decisions/0009-granular-crud-admin-permissions.md.
 */
class ExpandAdminRbacPermissionActionsMigrationTest extends TestCase
{
    private function runMigration(): void
    {
        $migration = require base_path(
            'Modules/Core/database/migrations/2026_08_16_000000_expand_admin_rbac_permission_actions.php'
        );

        $migration->up();
    }

    public function test_manage_permission_expands_to_create_update_delete_for_every_holding_role(): void
    {
        $manage = Permission::findOrCreate('admins.manage', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->givePermissionTo($manage);

        $this->runMigration();

        $role->unsetRelation('permissions');
        $this->assertTrue($role->hasPermissionTo('admins.create'));
        $this->assertTrue($role->hasPermissionTo('admins.update'));
        $this->assertTrue($role->hasPermissionTo('admins.delete'));
        $this->assertFalse(Permission::where('name', 'admins.manage')->exists());
    }

    public function test_view_permission_is_kept_and_gains_list(): void
    {
        $view = Permission::findOrCreate('roles.view', 'admin');
        $role = Role::findOrCreate('Support', 'admin');
        $role->givePermissionTo($view);

        $this->runMigration();

        $role->unsetRelation('permissions');
        $this->assertTrue($role->hasPermissionTo('roles.view'));
        $this->assertTrue($role->hasPermissionTo('roles.list'));
    }

    public function test_admin_role_assignments_survive_the_migration_unchanged(): void
    {
        Permission::findOrCreate('admins.manage', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->givePermissionTo('admins.manage');

        $admin = Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $admin->assignRole($role);

        $this->runMigration();

        $this->assertTrue($admin->refresh()->hasRole('Ops'));
    }

    public function test_migration_is_a_no_op_when_the_old_permissions_no_longer_exist(): void
    {
        // No "before" state set up here — proves running it again (e.g. against a fresh install
        // that never had the old permissions) doesn't error or duplicate anything.
        $this->runMigration();

        $this->assertFalse(Permission::where('name', 'admins.manage')->exists());
        $this->assertFalse(Permission::where('name', 'roles.manage')->exists());
    }
}
