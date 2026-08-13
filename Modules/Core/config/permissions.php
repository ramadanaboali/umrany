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
        'admins.view',
        'admins.manage',
        'roles.view',
        'roles.manage',
    ],

    'roles' => [
        'Operations' => ['admins.view', 'admins.manage', 'roles.view', 'roles.manage'],
        'Support' => ['admins.view', 'roles.view'],
    ],
];
