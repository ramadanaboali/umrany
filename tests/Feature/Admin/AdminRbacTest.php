<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proves the dynamic RBAC model actually gates routes server-side (not just hides UI —
 * docs/business/personas.md's explicit rule), and that the Super Admin bypass works via the
 * is_super_admin boolean rather than any role name.
 */
class AdminRbacTest extends TestCase
{
    private function admin(bool $superAdmin = false): Admin
    {
        return Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => $superAdmin,
        ]);
    }

    public function test_admin_without_permission_is_forbidden_from_managing_admins(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/admins')
            ->assertForbidden();
    }

    public function test_admin_with_list_permission_can_list_but_not_create(): void
    {
        Permission::findOrCreate('admins.list', 'admin');
        Permission::findOrCreate('admins.create', 'admin');

        $role = Role::findOrCreate('Support', 'admin');
        $role->syncPermissions(['admins.list']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $this->actingAs($admin, 'admin')->get('/admin/admins')->assertOk();
        $this->actingAs($admin, 'admin')->get('/admin/admins/create')->assertForbidden();
    }

    public function test_super_admin_bypasses_every_permission_check_with_no_role_at_all(): void
    {
        $superAdmin = $this->admin(superAdmin: true);

        $this->actingAs($superAdmin, 'admin')->get('/admin/admins')->assertOk();
        $this->actingAs($superAdmin, 'admin')->get('/admin/admins/create')->assertOk();
        $this->actingAs($superAdmin, 'admin')->get('/admin/roles')->assertOk();
    }
}
