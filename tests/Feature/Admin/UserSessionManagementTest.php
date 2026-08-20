<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSessionManagementTest extends TestCase
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

    public function test_listing_users_is_forbidden_without_users_list_permission(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_with_users_list_permission_can_list_users(): void
    {
        Permission::findOrCreate('users.list', 'admin');
        $role = Role::findOrCreate('Support', 'admin');
        $role->syncPermissions(['users.list']);

        $admin = $this->admin();
        $admin->assignRole($role);

        User::factory()->create(['name' => 'Visible User']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Visible User');
    }

    public function test_viewing_a_user_is_forbidden_without_users_view_permission(): void
    {
        Permission::findOrCreate('users.list', 'admin');
        $role = Role::findOrCreate('Support', 'admin');
        $role->syncPermissions(['users.list']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get("/admin/users/{$user->id}")
            ->assertForbidden();
    }

    public function test_admin_with_users_view_permission_can_view_a_user(): void
    {
        Permission::findOrCreate('users.view', 'admin');
        $role = Role::findOrCreate('Viewer', 'admin');
        $role->syncPermissions(['users.view']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create(['name' => 'Detail User']);

        $this->actingAs($admin, 'admin')
            ->get("/admin/users/{$user->id}")
            ->assertOk()
            ->assertSee('Detail User');
    }

    public function test_destroying_sessions_is_forbidden_without_users_update_permission(): void
    {
        Permission::findOrCreate('users.view', 'admin');
        $role = Role::findOrCreate('Viewer', 'admin');
        $role->syncPermissions(['users.view']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->delete("/admin/users/{$user->id}/sessions")
            ->assertForbidden();
    }

    public function test_admin_with_users_update_permission_can_revoke_all_of_a_users_sessions(): void
    {
        Permission::findOrCreate('users.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['users.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create();
        $user->createToken('device-1');
        $user->createToken('device-2');

        $this->actingAs($admin, 'admin')
            ->delete("/admin/users/{$user->id}/sessions")
            ->assertRedirect(route('admin.users.show', $user))
            ->assertSessionHas('status');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_soft_deleted_user_does_not_appear_in_the_listing(): void
    {
        Permission::findOrCreate('users.list', 'admin');
        $role = Role::findOrCreate('Support', 'admin');
        $role->syncPermissions(['users.list']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create(['name' => 'Gone User']);
        $user->delete();

        $this->actingAs($admin, 'admin')
            ->get('/admin/users')
            ->assertOk()
            ->assertDontSee('Gone User');
    }
}
