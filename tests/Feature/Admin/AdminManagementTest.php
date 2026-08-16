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
 * The edge cases here are deliberate, not incidental — each one guards against an admin locking
 * themselves (or everyone) out of the system, or a non-super-admin escalating their own access.
 * See Modules\Core\Actions\Admin\UpdateAdmin.
 */
class AdminManagementTest extends TestCase
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

    public function test_super_admin_can_create_an_admin_with_a_role(): void
    {
        Role::findOrCreate('Finance', 'admin');
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')->post('/admin/admins', [
            'name' => 'Nora',
            'email' => 'nora@umrany.test',
            'password' => 'NoraPass123',
            'password_confirmation' => 'NoraPass123',
            'roles' => ['Finance'],
        ])->assertRedirect(route('admin.admins.index'));

        $nora = Admin::where('email', 'nora@umrany.test')->first();
        $this->assertNotNull($nora);
        $this->assertTrue($nora->hasRole('Finance'));
        $this->assertFalse($nora->is_super_admin);
    }

    public function test_duplicate_email_is_rejected_on_create(): void
    {
        Admin::forceCreate([
            'name' => 'Existing',
            'email' => 'taken@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')->post('/admin/admins', [
            'name' => 'Someone',
            'email' => 'taken@umrany.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertSessionHasErrors('email');
    }

    public function test_duplicate_phone_is_rejected_on_create_even_in_a_different_format(): void
    {
        Admin::forceCreate([
            'name' => 'Existing',
            'email' => 'existing-'.uniqid().'@umrany.test',
            'phone' => '+966512345678',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')->post('/admin/admins', [
            'name' => 'Someone',
            'email' => 'someone-'.uniqid().'@umrany.test',
            'phone' => '0512345678',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertSessionHasErrors('phone');
    }

    public function test_non_super_admin_cannot_grant_super_admin_even_if_submitted(): void
    {
        Permission::findOrCreate('admins.create', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['admins.create']);

        $actor = Admin::forceCreate([
            'name' => 'Ops Admin',
            'email' => 'ops@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $actor->assignRole($role);

        $this->actingAs($actor, 'admin')->post('/admin/admins', [
            'name' => 'Sneaky',
            'email' => 'sneaky@umrany.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'is_super_admin' => '1',
        ])->assertRedirect(route('admin.admins.index'));

        $sneaky = Admin::where('email', 'sneaky@umrany.test')->first();
        $this->assertNotNull($sneaky);
        $this->assertFalse($sneaky->is_super_admin, 'a non-super-admin must never be able to mint a super admin');
    }

    public function test_admin_cannot_suspend_their_own_account(): void
    {
        $actor = $this->superAdmin();

        $response = $this->actingAs($actor, 'admin')->put("/admin/admins/{$actor->id}", [
            'name' => $actor->name,
            'status' => 'suspended',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame(AdminStatus::Active, $actor->refresh()->status);
    }

    public function test_is_super_admin_is_never_changed_via_update_even_when_submitted_by_a_super_admin(): void
    {
        $target = $this->superAdmin();
        $actor = $this->superAdmin();

        $response = $this->actingAs($actor, 'admin')->put("/admin/admins/{$target->id}", [
            'name' => $target->name,
            'status' => 'active',
            'is_super_admin' => '0',
        ]);

        // The field is silently ignored, not honored — is_super_admin can only change via
        // core:admin:promote-super. See docs/architecture/admin-portal.md.
        $response->assertRedirect(route('admin.admins.index'));
        $this->assertTrue($target->refresh()->is_super_admin);
    }

    public function test_duplicate_phone_is_rejected_on_update(): void
    {
        Admin::forceCreate([
            'name' => 'Existing',
            'email' => 'existing-'.uniqid().'@umrany.test',
            'phone' => '+966512345678',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $target = $this->superAdmin();
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')->put("/admin/admins/{$target->id}", [
            'name' => $target->name,
            'status' => 'active',
            'phone' => '0512345678',
        ])->assertSessionHasErrors('phone');
    }

    public function test_keeping_an_admins_own_phone_unchanged_is_not_flagged_as_a_duplicate(): void
    {
        $target = Admin::forceCreate([
            'name' => 'Target',
            'email' => 'target-'.uniqid().'@umrany.test',
            'phone' => '+966512345678',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $actor = $this->superAdmin();

        $this->actingAs($actor, 'admin')->put("/admin/admins/{$target->id}", [
            'name' => $target->name,
            'status' => 'active',
            'phone' => '+966512345678',
        ])->assertSessionDoesntHaveErrors();
    }

    public function test_last_active_super_admin_cannot_be_suspended(): void
    {
        $onlySuperAdmin = $this->superAdmin();

        Permission::findOrCreate('admins.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->givePermissionTo('admins.update');
        $actor = Admin::forceCreate([
            'name' => 'Ops Admin',
            'email' => 'ops-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $actor->assignRole($role);

        $response = $this->actingAs($actor, 'admin')->put("/admin/admins/{$onlySuperAdmin->id}", [
            'name' => $onlySuperAdmin->name,
            'status' => 'suspended',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame(AdminStatus::Active, $onlySuperAdmin->refresh()->status);
    }
}
