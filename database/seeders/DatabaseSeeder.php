<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\AI\Database\Seeders\AIDatabaseSeeder;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\ECommerce\Database\Seeders\ECommerceDatabaseSeeder;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\Projects\Database\Seeders\ProjectsDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Every module seeder listed here must itself be
     * idempotent (updateOrCreate/firstOrCreate) — this runs on every container boot via
     * docker/app/entrypoint.sh, not just once. See docs/architecture/infrastructure.md.
     */
    public function run(): void
    {
        $this->call([
            CoreDatabaseSeeder::class,
            ProjectsDatabaseSeeder::class,
            ECommerceDatabaseSeeder::class,
            ERPDatabaseSeeder::class,
            AIDatabaseSeeder::class,
        ]);
    }
}
