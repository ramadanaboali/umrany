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
- **No data-remap migration** — this project is still pre-launch (no production data to preserve
  across the catalog shape change), so per root `CLAUDE.md`'s dev-phase migration policy, the
  catalog change is just the `config/permissions.php` edit plus a normal reseed
  (`composer reseed` / `migrate:fresh --seed`). `PermissionSeeder`/`RoleSeeder` already read the
  catalog generically, so a fresh install produces the new 10-permission catalog with no special
  handling. (An earlier version of this change shipped a one-time
  `Permission::findOrCreate`/`givePermissionTo` remap migration to preserve already-seeded roles'
  effective permissions across the rename — removed once the dev-phase policy made "just reseed"
  the simpler, equally-correct choice for an environment with no real users yet.)
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
  that bundling. Any admin/role data seeded before this change needs a fresh reseed to pick up the
  new catalog (expected and fine pre-launch; would need a real remap migration again post-launch).
