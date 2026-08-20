# 7. Admin dashboard is a Blade app inside this monolith, not a separate web app

## Status

Accepted

## Context

`docs/architecture/system-architecture.md` originally stated that this repository is API-only — no Blade views, no web routes anywhere — and that the admin portal would be a *separate* web application (like the Next.js customer app and the Flutter mobile app) consuming the same `/api/v1/*` surface as every other client.

Building the actual Identity/Auth/Provider/Admin-RBAC slice surfaced a concrete requirement: an admin needs to be able to log in, manage other admins, and manage dynamic roles/permissions through a real UI, seeded and usable immediately, without first standing up and deploying a second application/repository. Continuing to defer the admin UI to a hypothetical separate app would have left the RBAC/seeding work unverifiable by anyone without hand-rolling API calls.

Three sub-decisions were made together, since they constrain each other:

1. **Where does the admin UI's code live?** A new `Modules/Admin` package (consistent with the modular pattern, but the first module with web routes/Blade views — a precedent every other module's docs explicitly say doesn't exist) vs. a root-level `app/Http/Controllers/Admin` + `resources/views/admin` tree outside the module system entirely.
2. **How does the admin UI read/write business data?** Through the same `/api/v1/*` endpoints every other client uses, vs. in-process calls into each module's `Contracts\...`/`Services\...` classes.
3. **What builds the UI itself?** Livewire (the natural Laravel pairing for a no-separate-frontend admin panel) vs. hand-rolled Blade + a plain dashboard theme's HTML/CSS/JS, with server-rendered forms and full-page navigation.

## Decision

- **Admin dashboard lives at the application root**, not inside `Modules/*`: `app/Http/Controllers/Admin/*`, `resources/views/admin/*`, `routes/admin.php` (registered via `bootstrap/app.php`'s `withRouting(then: ...)`). This is the one deliberate exception to "every module is API-only" — recorded here instead of silently contradicting `system-architecture.md`. Root `app/` is allowed to depend on `Modules/Core` the same way every business module already does (Core is the one shared dependency in the whole application); it must never reach into another business module's Eloquent models directly, same rule as everywhere else.
- **Admin controllers call Core's `Services\...`/`Contracts\...`/`Models\...` classes in-process**, not through the HTTP API. No `/api/v1/admin/*` surface exists. This trades strict "every client is equal" purity (the API-first principle in `system-architecture.md`) for avoiding a redundant internal HTTP round-trip on every server-rendered page. If a future admin screen needs data from a business module (Projects, ECommerce, ERP, AI), it goes through that module's `Contracts\...` interface — the same one-way dependency rule as `module-boundaries.md` already describes, just consumed from `app/` instead of another `Modules/*` package.
- **Hand-rolled Blade, no Livewire.** Forms are plain `<form method="POST">` with full-page navigation; any client-side interactivity is left to whatever the eventual dashboard theme provides (Alpine/vanilla JS), not a server-driven component framework. The current views use a minimal placeholder layout/stylesheet standing in for the real theme — swapping it in later only touches `resources/views/admin/layouts/*.blade.php`.

  > **Update (ADR 0021):** this estimate proved wrong. The real theme swap also required updating
  > every inner admin view's markup (placeholder classes like `.checkbox-grid`/`.badge-active` had
  > no equivalent in the real theme's CSS), not just the two layout files. See
  > `docs/decisions/0021-velzon-material-admin-theme.md` and
  > `docs/architecture/admin-portal.md` § Theme and assets for what a theme swap and a new screen
  > each actually touch now.
- **Admin/Role/Permission remain Core-owned data** (`Modules\Core\Models\Admin`, `spatie/laravel-permission` scoped to the `admin` guard) regardless of where the UI lives — this was already the documented entity ownership in `docs/modules/core.md` and didn't need to change.

## Consequences

- `docs/architecture/system-architecture.md` needed an explicit carve-out — see the "API-first" section there — rather than continuing to state a blanket rule this decision now contradicts.
- The admin guard (`admin`, session-based) is a second authentication mechanism alongside the API's stateless Sanctum tokens, living in the same application. `config/auth.php` defines both; they never share state, and Octane's `FlushAuthenticationState` listener (already wired into `RequestReceived` in `config/octane.php`) prevents the admin guard's per-request `Auth::shouldUse()` mutation from leaking across requests in the same persistent worker — verified directly, not assumed, during implementation.
- A future admin screen touching Projects/ECommerce/ERP/AI data must still go through that module's `Contracts\...` interface. This decision does not grant the admin dashboard a blanket exemption from the module boundary rule — only from the "API-only" rule.
- If a genuine need for a *separate* admin web app ever emerges (e.g., a distinct ops team wanting their own deploy cadence), this decision would need revisiting — nothing here is irreversible, but reversing it means re-extracting the Blade layer into its own client consuming a new `/api/v1/admin/*` surface.
