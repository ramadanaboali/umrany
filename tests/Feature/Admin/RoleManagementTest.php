<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    private function superAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Super',
            'email' => 'super-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => true,
        ]);
    }

    public function test_super_admin_can_create_a_role_with_permissions(): void
    {
        Permission::findOrCreate('roles.view', 'admin');
        Permission::findOrCreate('roles.manage', 'admin');
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')->post('/admin/roles', [
            'name' => 'Marketing',
            'permissions' => ['roles.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Marketing')->where('guard_name', 'admin')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('roles.view'));
        $this->assertFalse($role->hasPermissionTo('roles.manage'));
    }

    public function test_role_name_must_be_unique_within_the_admin_guard(): void
    {
        Role::findOrCreate('Marketing', 'admin');
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')
            ->post('/admin/roles', ['name' => 'Marketing'])
            ->assertSessionHasErrors('name');
    }

    public function test_role_cannot_be_deleted_while_assigned_to_an_admin(): void
    {
        $role = Role::findOrCreate('Marketing', 'admin');
        $actor = $this->superAdmin();
        $actor->assignRole($role);

        $response = $this->actingAs($actor, 'admin')->delete("/admin/roles/{$role->id}");

        $response->assertSessionHasErrors('role');
        $this->assertNotNull(Role::find($role->id));
    }

    public function test_unused_role_can_be_deleted(): void
    {
        $role = Role::findOrCreate('Unused', 'admin');
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')
            ->delete("/admin/roles/{$role->id}")
            ->assertRedirect(route('admin.roles.index'));

        $this->assertNull(Role::find($role->id));
    }
}
