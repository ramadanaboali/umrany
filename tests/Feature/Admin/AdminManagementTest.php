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

    public function test_non_super_admin_cannot_grant_super_admin_even_if_submitted(): void
    {
        Permission::findOrCreate('admins.manage', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['admins.manage']);

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

    public function test_last_active_super_admin_cannot_be_demoted(): void
    {
        $onlySuperAdmin = $this->superAdmin();
        $otherActor = $this->superAdmin(); // acting admin is also super admin, editing the *other* one

        $response = $this->actingAs($otherActor, 'admin')->put("/admin/admins/{$onlySuperAdmin->id}", [
            'name' => $onlySuperAdmin->name,
            'status' => 'active',
            'is_super_admin' => '0',
        ]);

        // Two super admins exist at this point (onlySuperAdmin + otherActor), so this demotion is
        // actually legal — assert it succeeds, then prove the *true* last-one case separately.
        $response->assertRedirect(route('admin.admins.index'));
        $this->assertFalse($onlySuperAdmin->refresh()->is_super_admin);

        // Now demote otherActor too, leaving zero — not allowed on the last remaining one.
        $finalGuard = $this->superAdmin();
        $response = $this->actingAs($finalGuard, 'admin')->put("/admin/admins/{$otherActor->id}", [
            'name' => $otherActor->name,
            'status' => 'active',
            'is_super_admin' => '0',
        ]);
        $response->assertRedirect(route('admin.admins.index'));

        // Only $finalGuard remains a super admin now — attempting to demote *that* one must fail.
        $response = $this->actingAs($finalGuard, 'admin')->put("/admin/admins/{$finalGuard->id}", [
            'name' => $finalGuard->name,
            'status' => 'active',
            'is_super_admin' => '0',
        ]);
        $response->assertSessionHasErrors('status');
        $this->assertTrue($finalGuard->refresh()->is_super_admin);
    }
}
