<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;

class CoreDatabaseSeeder extends Seeder
{
    /**
     * Run in an order later seeders' data depends on: permissions before roles (roles assign
     * permissions), roles before the bootstrap admin isn't strictly required but keeps a
     * consistent story if AdminSeeder is ever extended to assign a role. Every seeder called
     * here is idempotent — see docs/architecture/infrastructure.md for why this runs on every
     * container boot, not just once.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            AdminSeeder::class,
            MasterDataSeeder::class,
        ]);

        if (! app()->environment('production')) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
