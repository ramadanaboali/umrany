<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Notifications\AccountActivatedNotification;
use Modules\Core\Notifications\AccountSuspendedNotification;
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

    /**
     * Regression tests for docs/decisions/0027-admin-user-suspend-reactivate.md.
     */
    public function test_suspending_a_user_is_forbidden_without_users_update_permission(): void
    {
        Permission::findOrCreate('users.view', 'admin');
        $role = Role::findOrCreate('Viewer', 'admin');
        $role->syncPermissions(['users.view']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post("/admin/users/{$user->id}/suspend", ['reason' => 'Reported for abuse'])
            ->assertForbidden();
    }

    public function test_admin_with_users_update_permission_can_suspend_a_user_with_a_reason(): void
    {
        Notification::fake();
        Permission::findOrCreate('users.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['users.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create();
        $user->createToken('device-1');

        $this->actingAs($admin, 'admin')
            ->post("/admin/users/{$user->id}/suspend", ['reason' => 'Reported for abuse'])
            ->assertRedirect(route('admin.users.show', $user))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame(UserStatus::Suspended, $user->status);
        $this->assertSame('Reported for abuse', $user->suspension_reason);
        $this->assertNotNull($user->suspended_at);
        $this->assertSame($admin->id, $user->suspended_by_admin_id);
        $this->assertSame(0, $user->tokens()->count(), 'suspending must immediately revoke sessions');
        Notification::assertSentTo($user, AccountSuspendedNotification::class);
    }

    public function test_suspend_requires_a_reason(): void
    {
        Permission::findOrCreate('users.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['users.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post("/admin/users/{$user->id}/suspend", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(UserStatus::Active, $user->refresh()->status);
    }

    public function test_admin_can_reactivate_a_suspended_user(): void
    {
        Notification::fake();
        Permission::findOrCreate('users.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['users.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create([
            'status' => UserStatus::Suspended,
            'suspension_reason' => 'Reported for abuse',
            'suspended_at' => now(),
            'suspended_by_admin_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->post("/admin/users/{$user->id}/reactivate")
            ->assertRedirect(route('admin.users.show', $user))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->suspension_reason);
        $this->assertNull($user->suspended_at);
        $this->assertNull($user->suspended_by_admin_id);
        Notification::assertSentTo($user, AccountActivatedNotification::class);
    }

    public function test_a_suspended_user_cannot_log_in_and_can_after_reactivation(): void
    {
        Permission::findOrCreate('users.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['users.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create(['email' => 'suspend-login@example.com', 'password' => 'Secr3tPass']);

        $this->actingAs($admin, 'admin')->post("/admin/users/{$user->id}/suspend", ['reason' => 'Investigation']);

        $this->postJson('/api/v1/core/auth/login', ['login' => 'suspend-login@example.com', 'password' => 'Secr3tPass'])
            ->assertStatus(422);

        $this->actingAs($admin, 'admin')->post("/admin/users/{$user->id}/reactivate");

        $this->postJson('/api/v1/core/auth/login', ['login' => 'suspend-login@example.com', 'password' => 'Secr3tPass'])
            ->assertOk();
    }

    /**
     * Regression tests for admin-initiated deletion, docs/decisions/0027-admin-user-suspend-
     * reactivate.md.
     */
    public function test_deleting_a_user_is_forbidden_without_users_delete_permission(): void
    {
        Permission::findOrCreate('users.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['users.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->delete("/admin/users/{$user->id}")
            ->assertForbidden();
    }

    public function test_admin_with_users_delete_permission_can_delete_a_user(): void
    {
        Permission::findOrCreate('users.delete', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['users.delete']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $user = User::factory()->create();
        $user->createToken('device-1');

        $this->actingAs($admin, 'admin')
            ->delete("/admin/users/{$user->id}")
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted($user);
        $this->assertSame(0, $user->tokens()->count());
    }
}
