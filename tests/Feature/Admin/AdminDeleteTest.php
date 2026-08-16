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
}
