<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncPermissionsCommandTest extends TestCase
{
    private function admin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
    }

    public function test_default_run_reseeds_the_permission_catalog_and_example_roles(): void
    {
        $this->artisan('core:sync-permissions')->assertSuccessful();

        $this->assertTrue(Permission::where('name', 'admins.view')->where('guard_name', 'admin')->exists());
        $this->assertTrue(Role::where('name', 'Operations')->where('guard_name', 'admin')->exists());
    }

    public function test_attach_grants_a_permission_to_a_role_and_every_admin_holding_it(): void
    {
        Role::findOrCreate('Reviewer', 'admin');
        $admin = $this->admin();
        $admin->assignRole('Reviewer');

        $this->artisan('core:sync-permissions', ['--attach' => 'providers.verify', '--role' => 'Reviewer'])
            ->assertSuccessful();

        $this->assertTrue($admin->hasPermissionTo('providers.verify'));
    }

    public function test_detach_revokes_a_permission_from_a_role(): void
    {
        $role = Role::findOrCreate('Reviewer', 'admin');
        $role->givePermissionTo(Permission::findOrCreate('providers.verify', 'admin'));

        $this->artisan('core:sync-permissions', ['--detach' => 'providers.verify', '--role' => 'Reviewer'])
            ->assertSuccessful();

        $this->assertFalse($role->hasPermissionTo('providers.verify'));
    }

    public function test_attach_without_role_fails(): void
    {
        $this->artisan('core:sync-permissions', ['--attach' => 'providers.verify'])->assertFailed();
    }

    public function test_attach_against_unknown_role_fails(): void
    {
        $this->artisan('core:sync-permissions', ['--attach' => 'providers.verify', '--role' => 'Ghost'])
            ->assertFailed();
    }

    public function test_default_run_preserves_a_dashboard_created_custom_role_and_its_permissions(): void
    {
        $custom = Role::findOrCreate('Reviewer', 'admin');
        $custom->givePermissionTo(Permission::findOrCreate('providers.verify', 'admin'));

        $this->artisan('core:sync-permissions')->assertSuccessful();

        $reloaded = Role::findByName('Reviewer', 'admin');
        $this->assertTrue($reloaded->hasPermissionTo('providers.verify'));
    }

    public function test_default_run_restores_an_admins_role_assignment_including_custom_roles(): void
    {
        Role::findOrCreate('Operations', 'admin');
        Role::findOrCreate('Reviewer', 'admin');
        $admin = $this->admin();
        $admin->assignRole(['Operations', 'Reviewer']);

        $this->artisan('core:sync-permissions')->assertSuccessful();

        $admin->refresh();
        $this->assertTrue($admin->hasRole('Operations'));
        $this->assertTrue($admin->hasRole('Reviewer'));
    }

    public function test_default_run_refuses_to_touch_a_non_admin_guard_role(): void
    {
        Role::findOrCreate('web-role', 'web');

        $this->artisan('core:sync-permissions')->assertFailed();

        $this->assertTrue(Role::where('name', 'web-role')->where('guard_name', 'web')->exists());
    }
}
