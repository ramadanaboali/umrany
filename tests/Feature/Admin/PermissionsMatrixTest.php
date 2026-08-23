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
 * Covers the permissions matrix screen after it stopped surfacing role assignments and gained
 * bilingual resource/action labels + pagination/search — no prior test exercised this route at
 * all, so this is new coverage, not a regression fix.
 */
class PermissionsMatrixTest extends TestCase
{
    private function admin(): Admin
    {
        $role = Role::findOrCreate('Viewer', 'admin');
        $role->syncPermissions(['roles.list']);

        $admin = Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
        ]);
        $admin->assignRole($role);

        return $admin;
    }

    public function test_matrix_renders_with_bilingual_labels_and_no_role_column(): void
    {
        Permission::findOrCreate('roles.list', 'admin');
        Permission::findOrCreate('users.list', 'admin');
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->get('/admin/permissions');

        $response->assertOk()
            ->assertSee(__('admin.permissions.resources.users'))
            ->assertSee(__('admin.permissions.actions.list'))
            ->assertDontSee('Viewer'); // the role name must not leak into this screen anymore
    }

    public function test_search_filters_resource_rows(): void
    {
        Permission::findOrCreate('roles.list', 'admin');
        Permission::findOrCreate('users.list', 'admin');
        Permission::findOrCreate('settings.view', 'admin');
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->get('/admin/permissions?search=users');

        $response->assertOk()
            ->assertSee(__('admin.permissions.resources.users'))
            ->assertDontSee(__('admin.permissions.resources.settings'));
    }
}
