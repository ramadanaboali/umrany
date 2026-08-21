<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDeleteTest extends TestCase
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

    public function test_super_admin_can_delete_a_regular_admin(): void
    {
        $actor = $this->admin(superAdmin: true);
        $target = $this->admin();

        $this->actingAs($actor, 'admin')
            ->delete("/admin/admins/{$target->id}")
            ->assertRedirect(route('admin.admins.index'));

        $this->assertSoftDeleted($target);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $actor = $this->admin(superAdmin: true);

        $response = $this->actingAs($actor, 'admin')->delete("/admin/admins/{$actor->id}");

        $response->assertSessionHasErrors('admin');
        $this->assertNotSoftDeleted($actor);
    }

    public function test_last_active_super_admin_cannot_be_deleted(): void
    {
        $onlySuperAdmin = $this->admin(superAdmin: true);
        $regular = $this->admin();
        // Grant the regular admin delete permission so this isn't blocked by authorization first.
        Permission::findOrCreate('admins.delete', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->givePermissionTo('admins.delete');
        $regular->assignRole($role);

        $response = $this->actingAs($regular, 'admin')->delete("/admin/admins/{$onlySuperAdmin->id}");

        $response->assertSessionHasErrors('admin');
        $this->assertNotSoftDeleted($onlySuperAdmin);
    }

    /**
     * Regression test for docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md's
     * extension to Admin — `admins` already had SoftDeletes but was left on plain unique indexes,
     * permanently blocking a soft-deleted admin's email/phone from ever being reused. A bare
     * "delete succeeds" test (see the others in this file) wouldn't catch that.
     */
    public function test_soft_deleted_admin_email_and_phone_can_be_reused(): void
    {
        $actor = $this->admin(superAdmin: true);
        $target = Admin::forceCreate([
            'name' => 'Reused Admin',
            'email' => 'reused@umrany.test',
            'phone' => '+966501111111',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
        ]);

        $this->actingAs($actor, 'admin')->delete("/admin/admins/{$target->id}")->assertRedirect();
        $this->assertSoftDeleted($target);

        Admin::forceCreate([
            'name' => 'New Owner',
            'email' => 'reused@umrany.test',
            'phone' => '+966501111111',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
        ]);

        $this->assertDatabaseCount('admins', 3); // actor + soft-deleted target + reused
    }
}
