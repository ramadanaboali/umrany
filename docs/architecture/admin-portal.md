# Admin Portal

The one deliberate exception to "every module is API-only" (see `system-architecture.md`) — a
session-based Blade dashboard living at the application root, not inside `Modules/*`. Full
rationale for why it's structured this way: `docs/decisions/0007-in-monolith-blade-admin.md`.

## Where the code lives

| Concern | Location | Why |
|---|---|---|
| `Admin` model, RBAC data (roles/permissions via `spatie/laravel-permission` on the `admin` guard), Repositories + Services (`AdminAuthService`, `AdminManagementService`, `RoleManagementService`) | `Modules/Core/app/*` | Admin/Role/Permission are Core-owned entities per `docs/modules/core.md` — this didn't change when the UI's placement was decided. |
| Controllers, FormRequests, Blade views, `routes/admin.php` | root `app/Http/Controllers/Admin`, `app/Http/Requests/Admin`, `resources/views/admin` | The UI layer is HTTP-specific and colocated with the routes that use it — never inside a `Modules/*` package, which stays API-only. |
| Theme partials/component, locale middleware, RTL helper, translations, vendored theme assets | `resources/views/admin/partials/*`, `resources/views/components/admin/*`, `app/Http/Middleware/SetAdminLocale`, `app/Support/AdminTheme`, root `lang/{en,ar}/admin.php`, `Modules/Core/lang/{en,ar}/*`, `public/vendor/velzon/` | See § Theme and assets / § Localization below. |

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
| Password reset | Custom OTP mechanism (`Modules\Core\Services\AuthService::requestPasswordReset()`/`resetPassword()`) — supports mobile-only accounts, enumeration-safe (always a generic response) | Laravel's native `Password` broker against the `admins` broker (`config/auth.php`) — admins always have an email, **and deliberately reveals whether the submitted email belongs to a real admin account** (`app/Http/Requests/Admin/ForgotPasswordRequest`) — see `docs/decisions/0011-admin-forgot-password-reveals-account-existence.md` for why this asymmetry with the end-user side is intentional, not an oversight |
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

## Users screen

`app/Http/Controllers/Admin/UserController` + `Modules\Core\Services\Admin\UserManagementService`
+ `resources/views/admin/users/{index,show}.blade.php` — a deliberately minimal, read-mostly
screen over `App\Models\User` (end users), not a full CRUD screen. It exists to let a permitted
admin see the registered-user population and terminate a user's sessions (e.g. responding to a
compromised-account report); it does **not** let an admin create, edit, or delete a user — end
users self-register and self-delete their own accounts (see `docs/decisions/0014-user-soft-
deletes-and-partial-unique-indexes.md`), so there is no "admin deletes a user" action to build.

This is why `Modules/Core/config/permissions.php` gives `users.*` only three actions —
`users.list`, `users.view`, `users.update` — instead of the five-action `list`/`view`/`create`/
`update`/`delete` pattern every other admin-manageable resource uses (`docs/decisions/0009-
granular-crud-admin-permissions.md`). This is a deliberate, documented asymmetry, not an
inconsistency to "fix" by adding `users.create`/`users.delete` permissions that would gate features
that don't exist. `users.update` currently covers exactly one action: revoking all of a target
user's sessions (`DELETE /admin/users/{user}/sessions`) — soft-deleted users are excluded from the
listing automatically (the repository queries through the model, so Eloquent's `SoftDeletes`
global scope applies with no extra code).

## Settings screen

`app/Http/Controllers/Admin/SiteSettingsController` + `Modules\Core\Services\Admin\
SiteSettingsService` + `resources/views/admin/settings/edit.blade.php` — manages the single
`Modules\Core\Models\SiteSetting` row (branding/contact/social config: site name/title, logo,
contact email/phone/address, social links). `GET|PUT /admin/settings`, plus
`DELETE /admin/settings/logo`.

Same asymmetry as the Users screen above: `settings.view`/`settings.update` is a 2-action
permission set, not the usual 5 — there is no list/create/delete for a singleton row that always
exists (`SiteSetting::current()`/`SiteSettingSeeder` guarantee it). The data is also readable
without authentication at `GET /api/v1/core/settings` for other clients — see
`docs/api/conventions.md` § Public, unauthenticated read endpoints.

## Theme and assets

The dashboard uses **Velzon Material** (a vendored Bootstrap 5 static export, vertical sidebar
layout), not a placeholder — see `docs/decisions/0021-velzon-material-admin-theme.md` for the full
rationale, including several deliberate omissions from the original export (a booby-trapped
`layout.js`, a broken-path `plugins.js`, the full "Layout Customizer" panel).

- **Assets**: a curated ~4.8 MB subset lives in `public/vendor/velzon/` (never hand-edited — a
  theme update overwrites this directory wholesale). Project-specific CSS/JS
  (`resources/css/admin.css`, `resources/js/admin-theme.js`) goes through the normal Vite pipeline
  alongside the existing `resources/js/admin.js` (Echo/Reverb permissions-changed banner); the
  theme's own pre-compiled CSS/JS is referenced via plain `asset()` calls, not bundled.
- **Layouts**: `resources/views/admin/layouts/{app,guest}.blade.php` compose a set of partials —
  `partials/{theme-mode-boot,head-assets,foot-scripts,foot-scripts-libs,topbar,sidebar,footer,
  language-switcher,theme-toggle,flash}.blade.php` — plus one reusable component,
  `<x-admin.page-header>`, that every inner view opens with.
- **What a new admin screen touches**: `partials/sidebar.blade.php` (one nav `<li>`, `@can`-gated,
  active state via `request()->routeIs()`) and the new view itself, which extends
  `layouts.app`/`layouts.guest` and opens with `<x-admin.page-header>`. This replaces the
  now-incorrect "only touches two layout files" estimate from `docs/decisions/0007-*.md` — see that
  ADR's update note.
- **Dark/light mode**: a single topbar toggle button, persisted both in `localStorage` (per
  browser) and, when signed in, on the account itself (`admins.theme_mode`, via a fire-and-forget
  `POST /admin/theme` — `App\Http\Controllers\Admin\ThemeController`) so it follows the admin
  across devices. See ADR 0021's update note for why this changed from browser-only, and for a
  real `app.js` crash that had silently disabled the toggle entirely before being caught and
  fixed.

## Localization (EN/AR, RTL)

Full detail: `docs/decisions/0022-admin-dashboard-en-ar-localization.md`.

- `admins.preferred_language` (`Modules\Core\Enums\Language`, same enum/shape as
  `user_profiles.preferred_language`) is the source of truth for a signed-in admin's language;
  `App\Http\Middleware\SetAdminLocale` (wrapping the *entire* `routes/admin.php` group in
  `bootstrap/app.php`, including the guest routes) resolves it each request: DB column → a
  long-lived `admin_locale` cookie (not the session — see ADR 0022's update note for why) →
  `Language::English`. It deliberately does not fall back to `config('app.locale')` — see the ADR
  for the feedback-loop bug that fallback caused. `AuthController::store()` adopts the cookie
  value into the DB on login, so a language picked on the login page actually sticks.
  `App\Support\AdminTheme` is the single place both layouts and `partials/head-assets.blade.php`
  read the active language/direction/stylesheet-suffix from.
- Switching language is `POST admin/locale` (`admin.locale.update`), reachable outside both the
  `guest:admin` and `auth:admin` groups and carrying no `can:` permission — a self-service
  preference like `profile.edit`/`update`.
- Translations: root `lang/{en,ar}/admin.php` (admin dashboard's own UI strings) and the framework
  files generated by `laravel-lang/common`; `Modules/Core/lang/{en,ar}/*` under an explicit
  `core::` namespace (see `CoreServiceProvider::registerTranslations()`) for Core service/rule
  messages that render inside admin screens. **Never create `resources/lang/`** — its mere
  existence relocates the whole application's translation root away from `lang/`.

## What's still open

- No `AuditLog` yet (`docs/modules/core.md` § Admin) — admin actions (login, role/permission
  changes, admin suspensions) aren't recorded to an immutable audit trail yet. Add it in the same
  change that a real compliance/audit requirement lands, not speculatively.
- Cross-product dashboards, SEO/CMS (`DynamicPage`/`SeoMetadata`), `SystemSetting`, and the
  internal `AdminCrmLead`/`AdminCrmActivity` sales CRM are Phase 7 per `docs/business/roadmap.md`
  and don't exist yet — `DashboardController` is deliberately thin until there's real
  cross-module data to aggregate.
- `AdminResetPasswordNotification` is still sent in the app's default locale regardless of the
  target admin's `preferred_language` — noted as a known gap in ADR 0022, not fixed there.
