# 8. Admin RBAC changes push a live-refresh signal over Reverb

## Status

Accepted

## Context

An admin's effective permissions (their role assignments, a role's permission set, their account
status) can be changed by another admin while the first admin is already signed in and looking at
a screen. Before this change, nothing told that browser tab anything had changed — the sidebar,
buttons, and route guards would keep reflecting stale permissions until the admin happened to
navigate again. `docs/modules/core.md`'s "Notable edge cases called out in the source doc" already
names this exact scenario ("role permissions change during an active admin session").

This application already has a proven, working pattern for exactly this shape of problem on the
end-user side: `Modules\Core\Events\UserCapabilitiesChanged` broadcasts on a private
`core.user.{id}` channel over Reverb whenever a user's computed capabilities might have changed.
The question was whether to extend that same pattern to the `admin` guard, or accept the gap and
rely on admins noticing stale state on their own.

Two mechanisms were considered for closing the gap:

1. **Full real-time push via Reverb** — mirror `UserCapabilitiesChanged`'s shape for the `admin`
   guard: a new broadcast event, a new private channel, and a client-side Echo listener.
2. **A lightweight version-check** — stamp a version on permission-affecting changes and compare it
   against a value captured at login on every request, forcing re-authentication on mismatch. No
   new real-time infrastructure, but only takes effect on the admin's next navigation/click, not
   while they're sitting on a page.

## Decision

Went with (1), reusing the existing pattern rather than inventing a second one:

- `Modules\Core\Events\AdminPermissionsChanged` — `ShouldBroadcast`, queued (`core-default`, never
  synchronous — Octane workers must never block on a broadcast), broadcasts `permissions.changed`
  on `private-core.admin.{id}`. Distinct channel name from `core.user.{id}` — different guard,
  different model, no collision.
- Fired from `RoleManagementService::update()` (fanned out to every admin currently holding that
  role, via Spatie's `Role::users()` relation) and `AdminManagementService::update()` (only when
  the target admin's status or role assignments actually changed — a plain name/phone edit isn't
  worth a live banner), and from `core:admin:promote-super` (see ADR-adjacent
  `docs/architecture/admin-portal.md` § Authorization).
- **Channel authorization for a non-default guard, without a new route or middleware**: Laravel's
  single global `/broadcasting/auth` route runs under `web` middleware (session-based), and
  `Broadcaster::retrieveUser()` supports a per-channel `guards` option
  (`Broadcast::channel('core.admin.{id}', fn (Admin $admin, int $id) => ..., ['guards' =>
  ['admin']])`). This resolves the channel's user via `Auth::guard('admin')` specifically, instead
  of the default `web`/`sanctum` guard `retrieveUser()` would otherwise use — no new route,
  middleware, or controller needed.
- **Client side is fully new** — the admin dashboard had zero Vite/JS wiring before this
  (`resources/views/admin/layouts/app.blade.php` had no `@vite(...)` at all). Added `laravel-echo`/
  `pusher-js`, a dedicated `resources/js/admin.js` entry point (kept separate from the main
  `resources/js/app.js` so the admin bundle stays minimal), and CSRF header wiring on
  `window.Echo`'s `auth` option so the private-channel subscription POST passes the `web`
  middleware's CSRF check.
- **A dismissible banner, not a forced reload.** The admin layout gets a hidden-by-default banner
  that un-hides on the broadcast event, linking to a manual refresh — never `location.reload()`,
  so an admin mid-form-fill isn't yanked away without warning.

## Consequences

- Every future admin-guard permission-affecting change should dispatch `AdminPermissionsChanged`
  for the affected admin(s), the same way every future end-user capability change should dispatch
  `UserCapabilitiesChanged` — both are now the established pattern for "tell a possibly-connected
  client this changed," just scoped to their respective guards.
- `core:sync-permissions`/`core:permissions:audit --sync` do **not** dispatch this event — they're
  rare ops commands, not everyday dashboard actions. If they start being run against a live
  production system with admins actively signed in, revisit this.
- The client-side Echo/Vite wiring introduced here (`resources/js/admin.js`, the CSRF meta tag, the
  banner markup) is the first JS the admin dashboard has ever shipped — future admin-side
  interactivity should extend this same entry point rather than starting a third one.
