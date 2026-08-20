# System Architecture

## Shape of the system

Umrany's backend is a single Laravel 13 application organized as a **modular monolith**, not a set of separately deployed services. There is one codebase, one Composer install, one database connection, one deploy artifact (`umrany_app:local`) — but internally the application is partitioned into five modules (`Modules/Core`, `Modules/Projects`, `Modules/ECommerce`, `Modules/ERP`, `Modules/AI`) using `nwidart/laravel-modules`. Each module is a self-contained slice: its own routes, controllers, models, migrations, service provider, and tests, living under `Modules/<Name>/`.

The monolith choice is deliberate for this stage of the product: five business domains (project bidding, marketplace, ERP, AI, and the shared platform underneath them) share one team, one release cadence, and heavy cross-domain data (a user's wallet, subscription, and identity are read by every module). Splitting into microservices now would mean re-solving distributed transactions and cross-service auth for no organizational benefit. The module boundaries exist so that if/when a module needs to be extracted into its own service, the seams are already there — see `module-boundaries.md`.

## API-first, no server-rendered UI — except the admin portal

Every module is API-only. There are no Blade views or web routes anywhere in `Modules/*` — each module registers routes exclusively through its `RouteServiceProvider`, mounted under `api/v1/<module-alias>` (e.g. `/api/v1/core/health`, `/api/v1/ecommerce/...`). Two separate client applications, each in its own repository, consume this backend purely over HTTPS/REST (plus WebSockets for realtime):

- **Web app** — a Next.js application for project owners and store customers.
- **Mobile app** — Flutter, targeting iOS and Android, used by contractors/suppliers in the field.

Both hit the same versioned `/api/v1/*` surface, authenticate the same way (Sanctum bearer tokens), and are documented the same way (Scramble, see `tech-stack.md`). This is what "API-first" means here in practice for these two: if a feature isn't reachable through `/api/v1/...`, it doesn't exist for either client. New endpoints are added via the `laravel-endpoint` skill so route, FormRequest, Resource, doc-block, and test all land together.

**The admin portal is the one deliberate exception**: a session-based Blade dashboard living at the application root (`app/Http/Controllers/Admin`, `resources/views/admin`, `routes/admin.php`) — not inside `Modules/*`, and not a separate repository/app as originally planned. See `docs/decisions/0007-in-monolith-blade-admin.md` for why, and `docs/architecture/admin-portal.md` for the full reference. It authenticates via a distinct session-based `admin` guard (never Sanctum), and its controllers call into `Modules/Core`'s `Actions`/`Contracts` classes in-process rather than through `/api/v1/*` — there is no `/api/v1/admin/*` surface. The module boundary rule still applies to it: an admin screen touching Projects/ECommerce/ERP/AI data goes through that module's `Contracts\...` interface, same as every business module already does with Core.

## Serving model: Octane on RoadRunner

The application is served by `laravel/octane` running the **RoadRunner** application server (not Swoole, not FrankenPHP). Octane boots the Laravel application once per worker process and keeps it resident in memory, dispatching each HTTP request to a booted worker instead of bootstrapping the framework from scratch on every request. This is what lets a PHP monolith serve at the throughput this platform needs without a rewrite. RoadRunner was chosen over Swoole because it doesn't require a PHP extension (it's a standalone Go binary that speaks a worker protocol to a PHP process), which keeps the Docker image simpler and avoids Swoole's coroutine-related gotchas with existing synchronous PHP libraries.

Because Octane workers are long-lived, request-scoped assumptions that hold true in classic PHP-per-request (php-fpm) deployments no longer hold — see the Octane persistent-worker gotcha in `infrastructure.md` for the concrete implication (never bind request/container state into a `singleton()`).

## Supporting services

Four backing services complete the runtime, each with a single, narrow job:

- **PostgreSQL 15** — the system of record. One database, one schema, shared by all five modules' migrations (each module ships its own `database/migrations`, but they land in the same physical database — there is no per-module database isolation). A deliberate deviation from the source requirements doc's suggested MySQL 8 — see `docs/decisions/0005-postgresql-over-mysql.md`.
- **Redis 7.2** — backs two independent concerns: Horizon's queue driver and the application cache. Accessed via the phpredis PECL extension (see `tech-stack.md` for why).
- **Horizon** — supervises and monitors Redis-backed queue workers, one supervisor per module queue (`config/horizon.php`), with a dashboard at `/horizon`.
- **Reverb** — a first-party WebSocket server for realtime features (chat in Core, live bid/order updates in Projects/ECommerce). Clients connect directly to Reverb's port (fronted by nginx for the `/app/*` upgrade path); broadcast events are published from the app/queue workers over Redis and pushed to Reverb.

nginx sits in front of both the Octane app and Reverb, routing WebSocket upgrade traffic (`/app/*`) to Reverb and everything else to the Octane upstream. See `infrastructure.md` for the full docker-compose topology, port map, and naming conventions for cache keys, queues, and broadcast channels.

## Why this split holds up

The module boundary rule (Core-only shared dependency, no business module reaching into another's models — detailed in `module-boundaries.md`) is what keeps this monolith from becoming a ball of mud as it grows to five domains. It's enforced by convention and a review skill (`.claude/skills/module-boundary-check`) rather than a build-time tool, which is a conscious tradeoff: it costs nothing at compile time but requires the boundary check to actually be run on every PR that touches more than one module.
