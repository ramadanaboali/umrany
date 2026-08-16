<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Events\AdminPermissionsChanged;
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
        Permission::findOrCreate('roles.list', 'admin');
        Permission::findOrCreate('roles.create', 'admin');
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')->post('/admin/roles', [
            'name' => 'Marketing',
            'permissions' => ['roles.list'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Marketing')->where('guard_name', 'admin')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('roles.list'));
        $this->assertFalse($role->hasPermissionTo('roles.create'));
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

    public function test_updating_a_roles_permissions_notifies_every_admin_holding_it(): void
    {
        Event::fake([AdminPermissionsChanged::class]);
        Permission::findOrCreate('roles.list', 'admin');

        $role = Role::findOrCreate('Marketing', 'admin');
        $holder = Admin::forceCreate([
            'name' => 'Holder',
            'email' => 'holder-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $holder->assignRole($role);
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')->put("/admin/roles/{$role->id}", [
            'name' => 'Marketing',
            'permissions' => ['roles.list'],
        ])->assertRedirect(route('admin.roles.index'));

        Event::assertDispatched(AdminPermissionsChanged::class, fn ($event) => $event->adminId === $holder->id);
    }
}
