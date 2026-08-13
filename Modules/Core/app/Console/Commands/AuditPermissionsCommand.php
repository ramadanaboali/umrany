<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cross-checks every `can:<permission>` route middleware actually in use against
 * `Modules/Core/config/permissions.php`'s catalog and the database. Catches the two classes of
 * drift root CLAUDE.md Rule 0 exists to prevent:
 *
 * - A route references a permission never added to the catalog/database — Spatie's `can:`
 *   middleware throws PermissionDoesNotExist for these at request time, so this is a real
 *   500-in-production risk, not just a doc mismatch.
 * - A permission sits in the catalog but no route references it anymore — likely dead, though a
 *   Blade `@can` check elsewhere could still be using it, so this is reported, never
 *   auto-removed.
 *
 * `--sync` creates any route-referenced-but-missing permission directly in the database. It
 * deliberately does NOT edit permissions.php — whether a permission belongs in the human-curated
 * catalog is a decision for whoever wrote the route, not this command.
 */
final class AuditPermissionsCommand extends Command
{
    protected $signature = 'core:permissions:audit {--sync : Create any route-referenced permission missing from the database}';

    protected $description = 'Diff can:<permission> route middleware against the permission catalog and database.';

    public function handle(Router $router): int
    {
        $usedInRoutes = $this->permissionsUsedInRoutes($router);
        $catalog = config('core.permissions.catalog', []);
        $inDatabase = Permission::where('guard_name', 'admin')->pluck('name')->all();

        $missingFromCatalog = array_values(array_diff($usedInRoutes, $catalog));
        $missingFromDatabase = array_values(array_diff($usedInRoutes, $inDatabase));
        $unusedInCatalog = array_values(array_diff($catalog, $usedInRoutes));

        $this->reportList('Referenced by a route but missing from Modules/Core/config/permissions.php:', $missingFromCatalog, 'warn');
        $this->reportList('Referenced by a route but not seeded into the database yet:', $missingFromDatabase, 'warn');
        $this->reportList('In the catalog but not referenced by any route (a Blade @can check may still use it — verify before removing):', $unusedInCatalog, 'comment');

        if ($this->option('sync') && $missingFromDatabase !== []) {
            foreach ($missingFromDatabase as $name) {
                Permission::findOrCreate($name, 'admin');
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('Created '.count($missingFromDatabase).' missing permission(s) in the database.');
        }

        if ($missingFromCatalog === [] && $missingFromDatabase === [] && $unusedInCatalog === []) {
            $this->info('Routes, the permission catalog, and the database are all in sync.');
        }

        return $missingFromCatalog === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int, string>  $items
     */
    private function reportList(string $heading, array $items, string $style): void
    {
        if ($items === []) {
            return;
        }

        $this->{$style}($heading);
        foreach ($items as $item) {
            $this->line("  - {$item}");
        }
    }

    /**
     * @return array<int, string>
     */
    private function permissionsUsedInRoutes(Router $router): array
    {
        $permissions = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (str_starts_with($middleware, 'can:')) {
                    $permissions[] = explode(',', substr($middleware, 4))[0];
                }
            }
        }

        return array_values(array_unique($permissions));
    }
}
