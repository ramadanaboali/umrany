# 9. Admin RBAC permissions split into five CRUD actions per resource

## Status

Accepted

## Context

`Modules/Core/config/permissions.php`'s catalog originally had exactly two permissions per
resource — `<resource>.view` (covering both listing and viewing a single record) and
`<resource>.manage` (covering create, update, *and* delete bundled together) — across the two
resources that existed (`admins`, `roles`). This was coarser than what the admin dashboard's own
buttons actually needed: a role granted `admins.manage` could delete an admin even if the intent
was only to let them create one, and there was no way to grant "can view but not create" separately
from "can view but not delete." `docs/business/personas.md`'s permission-naming example (`resource.
action`, e.g. `wallets.adjust`, `withdrawals.approve`) already pointed at action-level granularity
as the intended shape — the two-permission catalog was a placeholder, not the target.

The admin dashboard's actual buttons are five distinct actions per resource: list, view, create,
update, delete. Splitting the catalog to match them is a bigger structural change than it looks,
because existing roles already hold the coarse permissions, and a plain rename would silently
change what those roles can do (a role holding `admins.manage` implicitly could delete admins;
after a naive rename to just `admins.update`, it couldn't anymore, without anyone deciding that).

## Decision

- New catalog: `list`, `view`, `create`, `update`, `delete` per resource — `admins.list`,
  `admins.view`, `admins.create`, `admins.update`, `admins.delete`, and the same five for `roles`.
  `.view` itself is kept (not renamed) since it remains meaningful — viewing one record is still a
  distinct action from listing many.
- **A one-time data migration, not a seeder change**, does the remap:
  `Modules/Core/database/migrations/2026_08_16_000000_expand_admin_rbac_permission_actions.php`.
  Every role holding `<resource>.manage` gets `<resource>.create` + `<resource>.update` +
  `<resource>.delete`; every role holding `<resource>.view` additionally gets `<resource>.list`.
  The old `.manage` permission is then deleted (cascading its `role_has_permissions` rows); `.view`
  is left alone since it's still valid. A real migration (not `PermissionSeeder`/`RoleSeeder`,
  which are idempotent-by-design and re-run on every container boot per root `CLAUDE.md` Rule 6) is
  the right tool here: this is a one-time structural remap of existing data, and Laravel's
  migrations table already guarantees "runs exactly once," which idempotent-seeder re-running
  would otherwise just approximate. The migration is guarded to be a safe no-op if the old
  permissions don't exist (fresh install, or already migrated).
- `down()` is intentionally empty — collapsing five granular grants back into the old two-
  permission shape has no well-defined inverse (a role could hold `admins.update` without
  `admins.create`, which `.manage` can't represent). A lossy `down()` that silently discarded which
  specific actions a role had would be worse than no `down()` at all.
- Every `can:<permission>` route middleware in `routes/admin.php`, the corresponding Blade `@can`
  checks, and the read-only Permissions catalog page (now a resource × action matrix instead of a
  flat per-permission list) were updated to the new names in the same change.
- `admins.delete` didn't correspond to any real feature before this change (only `roles.destroy`
  existed) — building the actual admin-delete route/controller/button happened in the same change,
  so the new permission isn't dead weight from the moment it's seeded.

## Consequences

- Any future resource added to the admin dashboard should get the same five-action treatment from
  the start, not the old two-permission shape — there's no longer a "coarse" example to copy from.
- `core:permissions:audit`/`core:sync-permissions` needed no changes — both already read the
  catalog/routes generically rather than assuming a fixed permission count.
- A role assigned `admins.update` today does **not** implicitly get `admins.delete` the way
  `admins.manage` used to imply it — this is a real behavior change for any role that relied on
  that bundling, now made explicit at migration time rather than left as an accidental side effect
  of a rename.
