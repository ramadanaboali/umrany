# 25. API versioning enforced by a standing test, not route-group structure

## Status

Accepted

## Context

`docs/api/conventions.md` documents `/api/v1/<module-alias>/...` as the versioning convention, but
nothing structurally requires it: every module's `RouteServiceProvider::mapApiRoutes()` only ever
applies `Route::middleware('api')->prefix('api')->group(...)` — the `v1/<alias>` segment is added
by each module's own `routes/api.php` purely by discipline. Auditing every registered route found
exactly one real violation: root `routes/api.php` still carried Laravel's default
`GET /user` stub (`{"data": ...}`-less, unauthenticated-user endpoint), completely unversioned and
confirmed dead — zero references anywhere in the app, and no first-party client uses it (auth goes
through `/api/v1/core/auth/*`, current-user data through `/api/v1/core/profile`).

## Decision

- Removed the dead `GET /user` stub outright.
- Added `tests/Feature/ApiVersioningTest.php`: enumerates every registered route, filters to ones
  carrying `api` in `gatherMiddleware()` (confirmed reliable — Horizon, Reverb, Sanctum's
  csrf-cookie, the API-docs routes, and every admin/web route never carry `api`), and asserts every
  one matches `^api/v\d+/`. This is a **test-level** guardrail, not a structural one (e.g. a base
  `RouteServiceProvider` method that forces every module's group under a version prefix) —
  deliberately: each module already declares its own version prefix inline for good reason (a
  module could theoretically run v1 and v2 concurrently during a future migration), so centralizing
  the prefix at the provider level would remove that flexibility for a guarantee a test can give
  just as reliably.
- No forward-looking "how would v2 be introduced" policy was written into
  `docs/api/conventions.md` in this pass — that's speculative until a v2 is actually needed (root
  `CLAUDE.md` Rule 0). This test already accepts any `v\d+` segment, so it needs no change the day
  a v2 actually exists.

## Consequences

- Any future route added to any module's `routes/api.php` (or, again, to the root file) without a
  version segment now fails CI immediately instead of shipping silently unversioned — this is the
  enforcement mechanism the prose-only convention previously lacked.
- Verified as a genuine regression guard, not a vacuous pass: temporarily restoring the removed
  `GET /user` stub reproduces a real test failure (`Found unversioned route(s) in the 'api'
  middleware group: GET /api/user`) before being reverted.
