<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Admin;

/**
 * Bootstrap Super Admin — the "how does anyone get into the admin dashboard the very first
 * time" answer. Credentials come from the environment, never committed; ADMIN_PASSWORD in
 * .env.example is a local-only placeholder and must be overridden for any shared environment.
 *
 * updateOrCreate keyed on email — re-running this (every deploy, per
 * docs/architecture/infrastructure.md) never duplicates the account, and picking up a changed
 * ADMIN_PASSWORD env value here is an intentional, documented way to rotate it.
 */
final class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->updateOrCreate(
            ['email' => config('core.bootstrap_admin.email')],
            [
                'name' => config('core.bootstrap_admin.name'),
                'password' => config('core.bootstrap_admin.password'),
                'is_super_admin' => true,
                'status' => 'active',
            ],
        );
    }
}
