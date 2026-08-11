# Tech Stack

Everything below is installed and was verified working end-to-end this session (not just added to `composer.json`). Where a choice was made between competing options, the reasoning is recorded here rather than left implicit — in particular, phpredis-vs-predis and Scribe-vs-l5-swagger were decided after an explicit research pass this session, not picked arbitrarily.

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

## Supporting Spatie/utility packages

| Package | Rationale |
|---|---|
| `spatie/laravel-medialibrary` | File/image uploads (product photos, verification documents, chat attachments) with a consistent conversions/storage model instead of hand-rolled file handling per module. |
| `spatie/laravel-query-builder` | Consistent, declarative API filtering/sorting/including across all five modules' index endpoints instead of ad hoc `$request->get(...)` query parsing in every controller. |
| `spatie/laravel-data` | Typed DTOs for data crossing module/API boundaries — pairs naturally with the FormRequest → Action → Resource controller pattern in `conventions.md`. |

## API documentation

| Package | Version | Rationale |
|---|---|---|
| `knuckleswtf/scribe` | v5.11 | Chosen over `darkaonline/l5-swagger` after this session's research pass: Scribe generates docs by inspecting the actual registered route list at generation time, whereas l5-swagger relies on scanning directories for OpenAPI annotations. With five independently-registered module route files, Scribe's approach means zero per-module config — the default `config/scribe.php` `prefixes: ['api/*']` already matches every module's routes with no per-module wiring. Configured with `type => 'laravel'`; serves interactive docs at `/docs`, the OpenAPI spec at `/docs.openapi`, and a Postman collection at `/docs.postman`. Scribe's auth section is configured for Sanctum bearer tokens (`Authorization` header), matching the auth stack above. |

## Dev tooling

| Package | Rationale |
|---|---|
| `laravel/pint` | Code style, run via `composer lint`. |
| `larastan/larastan` | Static analysis, run via `composer analyse` — required clean before merge (`conventions.md`). |
| `pestphp/pest` + `pestphp/pest-plugin-laravel` | Test framework; minimum one feature test per endpoint (`conventions.md`). |
| `barryvdh/laravel-debugbar` | Local/dev-only request/query inspection; not loaded outside the `local` environment. |
