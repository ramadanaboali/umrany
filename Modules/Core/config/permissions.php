<?php

declare(strict_types=1);

/**
 * The single source of truth for the admin-guard permission catalog and the code-defined example
 * roles — read by PermissionSeeder/RoleSeeder, and cross-checked against actual route middleware
 * by `core:permissions:audit`. Add a permission here in the SAME change that adds a
 * `can:<permission>` middleware to a route (root CLAUDE.md Rule 0) — don't pre-seed permissions
 * for screens that don't exist yet.
 *
 * `roles` below are illustrative starting points, not fixed identities (docs/business/personas.md)
 * — an admin may create additional roles through the dashboard; those live only in the database,
 * never here, and `core:sync-permissions`' full resync preserves them (see that command's
 * docblock for why a plain truncate-and-reseed would otherwise destroy them).
 */
return [
    'catalog' => [
        'admins.list',
        'admins.view',
        'admins.create',
        'admins.update',
        'admins.delete',
        'roles.list',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        // Deliberately 3 actions, not the usual 5 (docs/decisions/0009-granular-crud-admin-
        // permissions.md's convention) — end users self-register and self-delete their own
        // accounts (see docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md),
        // there is no admin "create a user"/"delete a user" screen to gate. `users.update` covers
        // the one mutating admin action that exists: revoking a user's sessions.
        'users.list',
        'users.view',
        'users.update',
        // Deliberately 2 actions, not 5 — `SiteSetting` (Modules\Core\Models\SiteSetting) is a
        // singleton row that always exists (Modules\Core\Database\Seeders\SiteSettingSeeder),
        // so there's no list/create/delete screen to gate. See docs/architecture/admin-portal.md.
        'settings.view',
        'settings.update',
    ],

    'roles' => [
        'Operations' => [
            'admins.list', 'admins.view', 'admins.create', 'admins.update', 'admins.delete',
            'roles.list', 'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'users.list', 'users.view', 'users.update',
            'settings.view', 'settings.update',
        ],
        'Support' => ['admins.list', 'admins.view', 'roles.list', 'roles.view', 'users.list', 'users.view', 'settings.view'],
    ],
];
