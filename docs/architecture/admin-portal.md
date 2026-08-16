# Admin Portal

The one deliberate exception to "every module is API-only" (see `system-architecture.md`) — a
session-based Blade dashboard living at the application root, not inside `Modules/*`. Full
rationale for why it's structured this way: `docs/decisions/0007-in-monolith-blade-admin.md`.

## Where the code lives

| Concern | Location | Why |
|---|---|---|
| `Admin` model, RBAC data (roles/permissions via `spatie/laravel-permission` on the `admin` guard), Repositories + Services (`AdminAuthService`, `AdminManagementService`, `RoleManagementService`) | `Modules/Core/app/*` | Admin/Role/Permission are Core-owned entities per `docs/modules/core.md` — this didn't change when the UI's placement was decided. |
| Controllers, FormRequests, Blade views, `routes/admin.php` | root `app/Http/Controllers/Admin`, `app/Http/Requests/Admin`, `resources/views/admin` | The UI layer is HTTP-specific and colocated with the routes that use it — never inside a `Modules/*` package, which stays API-only. |

If you're adding a new admin screen: business logic (a `Service` class calling a `Repository`,
validation rules independent of the HTTP layer — see `docs/architecture/backend-layering.md`)
goes in `Modules/Core/app/Services/Admin/*` if it's Core's own concern (managing admins/roles), or
is called via that business module's `Contracts\...` interface if the screen is about
Projects/ECommerce/ERP/AI data. The controller/request/view around it goes in
`app/Http/Controllers/Admin`, `app/Http/Requests/Admin`, `resources/views/admin` respectively.

## Authentication

Two independent identities exist in this application, never conflated:

| | End users | Admins |
|---|---|---|
| Model | `App\Models\User` | `Modules\Core\Models\Admin` |
| Guard | `sanctum` (stateless bearer tokens) | `admin` (session-based) |
| Password reset | Custom OTP mechanism (`Modules\Core\Services\AuthService::requestPasswordReset()`/`resetPassword()`) — supports mobile-only accounts | Laravel's native `Password` broker against the `admins` broker (`config/auth.php`) — admins always have an email |
| Roles/permissions | None — capability is computed, see `Contracts\UserCapabilityResolver` | `spatie/laravel-permission`, scoped to the `admin` guard |

`Admin` deliberately does not use `HasApiTokens` — there is no `/api/v1/admin/*` surface, so
admins never need a Sanctum token. `User` deliberately does not use `HasRoles` — see
`module-boundaries.md` § User capability resolution for why end-user capability is never a
stored role.

### The Octane guard-leak question, answered

Laravel's `auth:<guard>` middleware calls `Auth::shouldUse($guard)` on success, which mutates the
`AuthManager` singleton's resolved default driver for the rest of that request — this is how
`Auth::user()`/`Gate::check()`/`@can` correctly resolve the `admin` guard's user even though
`Gate`'s underlying user resolver has no guard-specific awareness of its own. Because Octane keeps
worker processes alive across many requests, the natural next question is whether that mutation
leaks into the *next* request served by the same worker. It doesn't: `config/octane.php`'s
`RequestReceived` listeners include `Laravel\Octane\Listeners\FlushAuthenticationState` (part of
Octane's `prepareApplicationForNextRequest()` default set), which resets guard state before every
new request. This was verified directly (not assumed) while building this feature — see the
`AdminRbacTest`/`AdminManagementTest` Pest tests, which exercise permission checks across many
simulated requests in the same test process.

## Authorization

- Route-level: `auth:admin` (is there a logged-in admin at all) + Spatie's `can:<permission>`
  middleware (does this admin have this specific permission) — see `routes/admin.php`.
- `Gate::before` in `Modules\Core\Providers\CoreServiceProvider::boot()` grants Super Admins
  unconditional access, keyed on the `admins.is_super_admin` boolean column — **never** on a role
  named "Super Admin". Role names are admin-created examples, not fixed identities
  (`docs/business/personas.md`), so a security bypass must never depend on one.
- Every protected controller action still works correctly even if the Blade sidebar link is
  hidden — hiding a UI control is never sufficient authorization on its own (the same rule the
  source spec states for admins generally).
- Non-obvious edge cases enforced in `Modules\Core\Services\Admin\AdminManagementService` (not
  just documented — see its Pest tests for proof): an admin can never suspend their own account
  through the edit form; the last active Super Admin can never be suspended or deleted, so there's
  always a way back into the system.
- **`is_super_admin` is never settable through the web dashboard or any API, by anyone — including
  another Super Admin.** The create/edit admin forms have no field for it at all, and
  `StoreAdminRequest`/`UpdateAdminRequest` don't accept it even if submitted directly. The only way
  to grant or revoke it is `php artisan core:admin:promote-super {email} [--revoke]`, which still
  enforces the last-active-Super-Admin guard on revoke. This is deliberately stricter than "only a
  Super Admin may change it" (the previous rule) — see `docs/decisions/0009-granular-crud-admin-
  permissions.md`'s sibling change and the removed UI copy that used to read "unrestricted access,
  bypasses all permission checks" next to the checkbox.
- **Super Admins are excluded from the admins listing and dashboard count** (`Admin::
  excludingSuperAdmins()`scope) — they aren't a "manageable" admin in the ordinary sense. A
  Super Admin still sees their own "Super Admin" badge on the dashboard (that's "who am I", not a
  listing).
- **Live permission refresh**: when a role's permissions change, or an admin's own role/status
  changes, `Modules\Core\Events\AdminPermissionsChanged` broadcasts on that admin's private
  `core.admin.{id}` channel (Reverb), and the admin layout shows a dismissible "your permissions
  changed — refresh" banner if they're already signed in elsewhere. See
  `docs/decisions/0008-admin-rbac-live-refresh-via-reverb.md`.

## Seeding

`Modules/Core/database/seeders/{PermissionSeeder,RoleSeeder,AdminSeeder}.php` — all idempotent
(`findOrCreate`/`updateOrCreate`), run on every container boot (`docs/architecture/infrastructure.md`
§ Startup migrate+seed). `PermissionSeeder`/`RoleSeeder` read the permission catalog and example
role → permission sets from `Modules/Core/config/permissions.php` (`config('core.permissions.*')`)
rather than a hardcoded list — that config file only names permissions for screens that actually
exist, one `list`/`view`/`create`/`update`/`delete` action per resource (`admins.list`,
`admins.view`, `admins.create`, `admins.update`, `admins.delete`, and the same five for `roles`) —
per root `CLAUDE.md` Rule 0/roadmap discipline, a new admin screen's permission gets added there in
the same change that builds the screen, not speculatively ahead of it. This replaced an earlier
coarse `view`/`manage` pair per resource — see `docs/decisions/0009-granular-crud-admin-permissions.md`
for why a fresh reseed (not a data-remap migration) was the right call at this project's current
pre-launch stage. `php artisan core:permissions:audit`
cross-checks that every `can:<permission>` route middleware actually in use has a matching catalog
entry and database row, and can `--sync` any database gap it finds. The bootstrap Super Admin's
credentials come from `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD` env vars, read via
`config('core.bootstrap_admin.*')` (`Modules/Core/config/config.php`) — never `env()` directly
outside a config file, since `env()` returns null once config is cached in a real deploy. Granting
or revoking Super Admin status is a separate, deliberately out-of-band operation — see
`php artisan core:admin:promote-super` in the Authorization section above.

## What's still open

- No `AuditLog` yet (`docs/modules/core.md` § Admin) — admin actions (login, role/permission
  changes, admin suspensions) aren't recorded to an immutable audit trail yet. Add it in the same
  change that a real compliance/audit requirement lands, not speculatively.
- Cross-product dashboards, SEO/CMS (`DynamicPage`/`SeoMetadata`), `SystemSetting`, and the
  internal `AdminCrmLead`/`AdminCrmActivity` sales CRM are Phase 7 per `docs/business/roadmap.md`
  and don't exist yet — `DashboardController` is deliberately thin until there's real
  cross-module data to aggregate.
- The dashboard theme integration is a placeholder (`resources/views/admin/layouts/*.blade.php`
  has minimal inline CSS) — swapping in a real theme only touches those two layout files.
