# Infrastructure

## docker-compose topology

Everything runs from the same application image (`umrany-app:local`, built from `docker/app/Dockerfile`: PHP 8.4-cli-bookworm with pdo_pgsql, pgsql, intl, zip, bcmath, pcntl, sockets, opcache with JIT enabled, exif, gd, redis, and igbinary extensions compiled in). The services differ only in the command they run against that shared image.

| Service | Runs | Port | Notes |
|---|---|---|---|
| `app` | `php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000` | 8000 | The Octane/RoadRunner HTTP worker pool; handles all `/api/v1/*` traffic. |
| `horizon` | `php artisan horizon` | — (no exposed port) | Supervises Redis-backed queue workers; dashboard is served through the `app` container at `/horizon`, not a separate port. |
| `reverb` | `php artisan reverb:start --host=0.0.0.0 --port=8080` | 8080 | WebSocket server for realtime broadcast (chat, live order/bid updates). |
| `scheduler` | `sh -c "while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done"` | — | Polls the Laravel scheduler every 60s; no built-in cron daemon in the container, so this loop is it. |
| `nginx` | `nginx:1.27-alpine` reverse proxy | 80 | Routes `/app/*` (the Reverb WebSocket upgrade path) to `reverb`; everything else to the `app` upstream. This is the single entry point clients (web, admin, mobile) actually hit. |
| `postgres` | `postgres:15-alpine` | 5434→5432 | System of record for all five modules; one schema, no per-module DB isolation. Host port is 5434 (not the default 5432) to avoid clashing with a locally-running Postgres on the host; the app talks to it over the internal Docker network at `postgres:5432` regardless — `DB_PORT=5432` in `.env` is the in-network port, not the host-mapped one. Chosen over the source doc's suggested MySQL 8 — see `docs/decisions/0005-postgresql-over-mysql.md`. |
| `redis` | `redis:7.2-alpine` | 6379 | Backs both the Horizon queue driver and the application cache. Accessed via phpredis + igbinary (see `tech-stack.md`). |

`app`, `horizon`, `reverb`, and `scheduler` all depend on `postgres` (health-checked) and `redis` (service-started) before booting. Only `app`, `reverb`, and `nginx` publish ports to the host; `horizon` and `scheduler` are internal-only processes reached through the `app` container (Horizon's dashboard) or not reached directly at all (`scheduler`).

## Dev/ops tooling served through the `app` container

Two things ride on top of the `app` container rather than being separate services:

- **Log viewer** (`opcodesio/log-viewer`) at `/log-viewer` — reads `storage/logs/*.log` directly (no separate log shipping/aggregation for local dev). Config at `config/log-viewer.php`. Its `require_auth_in_production` defaults to `true` — before deploying anywhere but local dev, define the `viewLogViewer` Gate it checks, or the route 403s for everyone.
- **Health checks** — `GET /api/v1/core/health` (liveness: process is up) and `GET /api/v1/core/health/ready` (readiness: DB + Redis + cache round-trip actually work, 503 if not) — see `Modules/Core/app/Http/Controllers/HealthController.php`. Both are outside `auth:sanctum`/`module.entitlement` on purpose. Laravel's own framework-level liveness probe is also still available at `/up` (configured in `bootstrap/app.php`).

## The Octane persistent-worker gotcha

Octane boots the Laravel container once per worker and reuses that same booted application across many subsequent requests, instead of the classic php-fpm model of a fresh container per request. This has one implication that matters directly for a modular app with five independently-registered module service providers:

**Anything bound as a Laravel `singleton()` persists in memory across requests, inside that worker, for as long as the worker lives.** If a module's service provider binds a singleton that captures request-specific or container-specific state (e.g. it stores the current authenticated user, injects the `Request` object at construction time, or caches a per-request value on first resolution), that state leaks into every subsequent request served by the same worker until it's recycled — a classic Octane bug class, and one that's easy to introduce accidentally when five modules are each registering their own bindings independently.

**Mitigation:**
- Prefer `scoped()` over `singleton()` for anything that should be recreated per request. Octane clears `scoped()` bindings between requests automatically; it does not touch `singleton()` bindings.
- Never inject `Illuminate\Http\Request` or the `Container` itself into a class bound as a `singleton()`. If a service genuinely needs per-request context, resolve the `Request` at call time (method injection or `app(Request::class)` inside the method), not at construction time.
- When in doubt in a module's service provider, default to `scoped()` — a slightly-more-often-rebuilt object is a much smaller risk than one that silently carries state from one user's request into another user's.

**Separately:** because Octane also caches route and config state across the worker's lifetime, enabling or disabling a module (or otherwise changing `config/modules.php` / module route registration) requires a full worker restart — `php artisan octane:reload` — not just `php artisan cache:clear` or `config:clear`. A `cache:clear` will not pick up a newly enabled/disabled module in an already-running Octane worker.

## Naming conventions

Three naming conventions keep Redis usage, queues, and broadcast channels legible across five independently-developed modules — every module follows the same `<module-alias>` scheme so it's always obvious at a glance which module owns a given key/queue/channel.

### Redis cache keys

Pattern: `umrany:<module>:<entity>:<id>`

- `umrany:ecommerce:product:42` — a cached product record from ECommerce.
- `umrany:projects:tender:1017` — a cached tender from Projects.
- `umrany:core:user:8` — a cached user profile from Core.

### Horizon queue names

Pattern: `<module-alias>-<priority>`, where priority is `high`, `default`, or `low`. Each queue gets its own supervisor entry in `config/horizon.php`, so a burst of low-priority AI jobs can never starve a high-priority ECommerce checkout job.

- `ecommerce-high` — checkout/payment-adjacent jobs.
- `ai-low` — BOQ analysis / offer comparison background jobs.
- `projects-default` — ordinary tender/quotation notification jobs.

### Reverb broadcast channels

Pattern: `private-<module-alias>.<entity>.<id>`

- `private-ecommerce.order.42` — realtime status updates for a single order.
- `private-projects.tender.1017` — realtime bid/quotation updates on a tender.
- `private-core.chat.8` — a private chat channel scoped to conversation/user 8.

All three conventions exist for the same reason: with five modules touching Redis and Reverb independently, a consistent `<module>` segment is what makes an ad hoc `redis-cli keys 'umrany:ecommerce:*'` or a Horizon queue-length graph filterable by module without cross-referencing code.
