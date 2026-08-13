<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Reads the example role → permission sets from `Modules/Core/config/permissions.php`
 * (`core.permissions.roles`) — that config file is the single place to add/edit one, not this
 * class. Role names here are illustrative, not fixed identities (docs/business/personas.md).
 * Super Admin is deliberately NOT a role here: it's the `admins.is_super_admin` boolean column,
 * checked via Gate::before in CoreServiceProvider, so it can never be broken by an admin
 * renaming/deleting a role.
 */
final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('core.permissions.roles', []) as $name => $permissions) {
            Role::findOrCreate($name, 'admin')->syncPermissions($permissions);
        }
    }
}
