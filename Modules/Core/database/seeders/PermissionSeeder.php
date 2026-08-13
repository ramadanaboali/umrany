<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reads the permission catalog from `Modules/Core/config/permissions.php` (`core.permissions.catalog`)
 * — that config file is the single place to add a new permission, not this class. See its docblock
 * for the "add it in the same change that builds the screen" rule.
 *
 * Idempotent — safe to run on every deploy (see docs/architecture/infrastructure.md).
 */
final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('core.permissions.catalog', []) as $name) {
            Permission::findOrCreate($name, 'admin');
        }

        // Octane keeps this app's PermissionRegistrar resolved for a worker's whole lifetime —
        // without this, a worker that booted before this seeder ran keeps enforcing a stale
        // permission list until it's recycled.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
