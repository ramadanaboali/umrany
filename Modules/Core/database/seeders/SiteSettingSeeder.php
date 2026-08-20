<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\SiteSetting;

/**
 * Guarantees the single site_settings row (id=1) exists — idempotent (SiteSetting::current()
 * uses firstOrCreate), safe to run on every container boot like every other seeder here.
 */
final class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::current();
    }
}
