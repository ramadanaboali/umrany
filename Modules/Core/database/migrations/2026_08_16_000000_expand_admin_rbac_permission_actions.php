<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * One-time remap from the old coarse `view`/`manage` permission catalog to the granular
     * `list`/`view`/`create`/`update`/`delete` catalog in Modules/Core/config/permissions.php —
     * see docs/decisions/0009-granular-crud-admin-permissions.md. Every role currently holding a
     * `.manage` permission gets the three action permissions it implied; every role holding
     * `.view` additionally gets `.list` (the old `.view` name is kept, not replaced, since it
     * remains valid in the new catalog). Runs exactly once (a real migration, not a seeder,
     * because CLAUDE.md Rule 6's idempotent-seeder requirement is the wrong tool for a one-time
     * data remap that must not silently re-run its role-grant logic on every container boot).
     * Guarded so it's a safe no-op on a fresh install (PermissionSeeder/RoleSeeder then seed the
     * new catalog from scratch) or a re-run after the old permissions are already gone.
     */
    public function up(): void
    {
        $map = [
            'admins.manage' => ['admins.create', 'admins.update', 'admins.delete'],
            'roles.manage' => ['roles.create', 'roles.update', 'roles.delete'],
            'admins.view' => ['admins.list'],
            'roles.view' => ['roles.list'],
        ];

        foreach ($map as $oldName => $newNames) {
            $old = Permission::query()->where('name', $oldName)->where('guard_name', 'admin')->first();

            if (! $old) {
                continue;
            }

            /** @var Collection<int, Role> $rolesHoldingOld */
            $rolesHoldingOld = $old->roles()->get();

            foreach ($newNames as $newName) {
                $new = Permission::findOrCreate($newName, 'admin');

                foreach ($rolesHoldingOld as $role) {
                    $role->givePermissionTo($new);
                }
            }

            // Only the coarse `.manage` permissions are fully replaced — `.view` remains a valid
            // permission in the new catalog, so it's kept as-is alongside the new `.list`.
            if (str_ends_with($oldName, '.manage')) {
                $old->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Deliberately not reversible: collapsing five granular grants back into the old two-
     * permission shape has no well-defined inverse (a role could hold `admins.update` without
     * `admins.create`, which `.manage` can't represent) — a lossy `down()` would silently discard
     * information, so none is provided.
     */
    public function down(): void {}
};
