<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Modules\Core\Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuditPermissionsCommandTest extends TestCase
{
    public function test_passes_when_routes_catalog_and_database_are_in_sync(): void
    {
        $this->artisan('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

        $this->artisan('core:permissions:audit')
            ->expectsOutputToContain('all in sync')
            ->assertSuccessful();
    }

    public function test_flags_a_permission_missing_from_the_database_and_sync_creates_it(): void
    {
        // core.permissions.catalog already has the full admins.*/roles.* CRUD catalog; the
        // admin.php routes reference exactly those, so nothing is missing from the DB out of the
        // box. Delete one to simulate the drift this command exists to catch.
        Permission::findOrCreate('roles.create', 'admin')->delete();

        $this->artisan('core:permissions:audit')
            ->expectsOutputToContain('roles.create')
            ->assertSuccessful();

        $this->assertFalse(Permission::where('name', 'roles.create')->exists());

        $this->artisan('core:permissions:audit', ['--sync' => true])->assertSuccessful();

        $this->assertTrue(Permission::where('name', 'roles.create')->where('guard_name', 'admin')->exists());
    }
}
