# Tech Stack

Everything below is installed and was verified working end-to-end this session (not just added to `composer.json`). Where a choice was made between competing options, the reasoning is recorded here rather than left implicit — in particular, phpredis-vs-predis and Scramble-vs-Scribe-vs-l5-swagger were decided after an explicit research pass, not picked arbitrarily.

## Core framework

| Package | Version | Rationale |
|---|---|---|
| PHP | 8.4 | Current stable; required by Laravel 13. |
| `laravel/framework` | 13.24 | Current LTS-track major; baseline for everything else here. |
| `nwidart/laravel-modules` | v13 | Gives the modular-monolith structure (per-module routes/config/migrations/providers, `module:make-*` scaffolding commands) without adopting a full microservices/multi-repo split the team doesn't need yet. See `module-boundaries.md` for what this buys architecturally. |

## Serving, realtime, and queues

| Package | Version | Rationale |
|---|---|---|
| `laravel/octane` | v2.18 | Keeps the framework booted in long-lived workers instead of re-bootstrapping per request — necessary for the throughput this platform targets from a single PHP monolith. |
| RoadRunner (Octane driver) | — | Chosen over Swoole/FrankenPHP because it's a standalone Go binary talking a worker protocol to PHP, not a PHP extension — simpler Docker image, no coroutine-compatibility gotchas with ordinary synchronous PHP/Composer packages. Verified serving requests on port 8000. |
| `laravel/reverb` | v1.11 | First-party WebSocket server for realtime features (chat, live bid/order updates), avoiding a third-party hosted socket service (e.g. Pusher) and its recurring cost/vendor lock-in. Verified with a real handshake (101 Switching Protocols + `pusher:connection_established`) on port 8080. |
| `laravel/horizon` | v5.48 | Dashboard and supervisor config for Redis-backed queue workers — one supervisor per module queue (see `infrastructure.md`). Verified dashboard reachable at `/horizon`. |

## Database

| Package | Version | Rationale |
|---|---|---|
| PostgreSQL | 15 | System of record for all five modules (one schema, no per-module DB isolation). See `docs/decisions/0005-postgresql-over-mysql.md` — a deliberate deviation from the source requirements doc's suggested MySQL 8. |

## Redis and cache/queue backend

| Package | Version | Rationale |
|---|---|---|
| Redis | 7.2 | Backs both the cache and Horizon's queue driver. |
| phpredis (PECL extension) | — | Chosen over the `predis` pure-PHP client after this session's research pass specifically for Horizon's job volume: phpredis is a C extension, so it avoids the per-call userland-PHP overhead predis carries, which matters once Horizon is pushing/popping jobs at sustained throughput. Tradeoff: phpredis isn't present in base PHP Docker images and must be compiled in via PECL — `docker/app/Dockerfile` does `pecl install redis && docker-php-ext-enable redis`. |
| igbinary (PECL extension) | — | Paired with phpredis as the serializer for cached/queued PHP values — more compact and faster to (de)serialize than PHP's native `serialize()`, which matters for both cache payload size and queue job throughput. Also compiled in via PECL in the same Dockerfile step. |

## Auth and authorization

| Package | Version | Rationale |
|---|---|---|
| `laravel/sanctum` | v4.3 | API token auth for both the SPA-style web app and the Flutter mobile app — lighter than full OAuth2 (Passport) for a first-party client set, and it's what the API-first, multi-client design in `system-architecture.md` needs. |
| `spatie/laravel-permission` | v8.3 | Roles/permissions backing the admin/RBAC surface in Core. Used so authorization checks go through Policies and permission checks, never raw role-string comparisons (`conventions.md`). |
| `pragmarx/google2fa` | v8.0 | TOTP generation/verification for authenticator-app MFA — see `docs/decisions/0012-totp-mfa-with-two-step-login.md`. `verifyKeyNewer()` (not plain `verifyKey()`) is used specifically for its replay protection. |
| `bacon/bacon-qr-code` | v3.0 | Renders the MFA enrollment QR code as inline SVG — no external QR-image service or extra client round trip. |

## Image processing

| Package | Version | Rationale |
|---|---|---|
| `intervention/image-laravel` | v4.1 | Avatar re-encoding (resize + format/quality normalization) on upload — see `docs/decisions/0019-intervention-image-for-avatar-optimization.md`. GD driver (already compiled into `docker/app/Dockerfile`, with `--with-webp` for the default WebP output format). `decodePath()`/`cover()`/`encode()` is this version's real API — not `read()`, which doesn't exist on v4 despite being a common assumption carried over from v2-style APIs. |

## Supporting Spatie/utility packages

| Package | Rationale |
|---|---|
| `spatie/laravel-medialibrary` | File/image uploads (product photos, verification documents, chat attachments) with a consistent conversions/storage model instead of hand-rolled file handling per module. |
| `spatie/laravel-query-builder` | Consistent, declarative API filtering/sorting/including across all five modules' index endpoints instead of ad hoc `$request->get(...)` query parsing in every controller. |
| `spatie/laravel-data` | Typed DTOs for data crossing module/API boundaries — pairs naturally with the FormRequest → Action → Resource controller pattern in `conventions.md`. |

## Admin dashboard frontend

| Package | Version | Rationale |
|---|---|---|
| Velzon Material (Themesbrand) | — (not Composer/npm-tracked) | Bootstrap 5 static HTML/CSS/JS export, vendored as a curated ~4.8 MB subset into `public/vendor/velzon/` rather than installed as a dependency — it ships no build source, so there's no package manifest to pin. `docs/decisions/0021-velzon-material-admin-theme.md` is the version/provenance record instead. |
| Bootstrap 5 (bundled with the theme) | — | Bundle JS (`libs/bootstrap/js/bootstrap.bundle.min.js`) for dropdowns/offcanvas/collapse; not a separate Composer/npm dependency of this app. |
| SimpleBar, node-waves, Feather Icons (bundled with the theme) | — | Sidebar scroll styling, button ripple effect, and one icon set used by the theme's own chrome — vendored alongside it, not installed separately. |
| `laravel-lang/common` (dev) | — | Generates complete Laravel framework translation files (`validation`, `auth`, `passwords`, `pagination`) for English + Arabic — see `docs/decisions/0022-admin-dashboard-en-ar-localization.md`. Dev-only; not needed at runtime once `lang/{en,ar}/*.php` are committed. |

Vite + Tailwind v4 (already listed above) now serve only `resources/css/app.css`/`resources/js/app.js` (the public `welcome.blade.php` page) plus the admin dashboard's own small `resources/css/admin.css`/`resources/js/admin-theme.js`/`resources/js/admin.js` — never the vendored Velzon assets, which are pre-compiled and referenced via plain `asset()` calls.

## API documentation

| Package | Version | Rationale |
|---|---|---|
| `dedoc/scramble` | v0.13.41 | Chosen over `knuckleswtf/scribe` (which itself was chosen over `darkaonline/l5-swagger`, see `docs/decisions/0003-scribe-over-l5swagger.md`) — see `docs/decisions/0023-scramble-over-scribe.md`. Documents via static analysis of FormRequest rules and Resource `toArray()` bodies rather than hand-written `@bodyParam`/`@response` annotations, with no regeneration step (`/docs/openapi.json` generates live). Route matching stays prefix-based against `api/*` (`config('scramble.api_path')`), preserving ADR 0003's "zero per-module config" property across all five modules. `security_strategy` set to `MiddlewareAuthSecurityStrategy` to auto-derive bearer-auth requirements from each route's `auth:sanctum` middleware. Docs URL kept at `/docs` (`Scramble::configure()->expose(...)` in `AppServiceProvider`) for continuity with every existing doc reference; OpenAPI 3.1, no Postman-collection equivalent (import the OpenAPI URL into Postman instead). |

## Dev tooling

| Package | Rationale |
|---|---|
| `laravel/pint` | Code style, run via `composer lint`. |
| `larastan/larastan` | Static analysis, run via `composer analyse` — required clean before merge (`conventions.md`). `phpstan.neon`'s `databaseMigrationsPath`/`configDirectories` explicitly list every module's `database/migrations`/`config` — Larastan's own defaults only scan the root ones, so without this every column/config value a module adds reads as a false "undefined property"/`noEnvCallsOutsideOfConfig` finding unrelated to the actual code. Add a new module's paths to both lists in the same change that scaffolds it. |
| `pestphp/pest` + `pestphp/pest-plugin-laravel` | Test framework; minimum one feature test per endpoint (`conventions.md`). |
| `barryvdh/laravel-debugbar` | Local/dev-only request/query inspection; not loaded outside the `local` environment. |
