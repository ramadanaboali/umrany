# Infrastructure

## docker-compose topology

Everything runs from the same application image (`umrany_app:local`, built from `docker/app/Dockerfile`: PHP 8.4-cli-bookworm with pdo_pgsql, pgsql, intl, zip, bcmath, pcntl, sockets, opcache with JIT enabled, exif, gd, redis, and igbinary extensions compiled in). The services differ only in the command they run against that shared image.

| Service | Container name | Runs | Port | Notes |
|---|---|---|---|---|
| `app` | `umrany_app` | `php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000` | 8000 | The Octane/RoadRunner HTTP worker pool; handles all `/api/v1/*` traffic. |
| `horizon` | `umrany_horizon` | `php artisan horizon` | — (no exposed port) | Supervises Redis-backed queue workers; dashboard is served through the `app` container at `/horizon`, not a separate port. |
| `reverb` | `umrany_reverb` | `php artisan reverb:start --host=0.0.0.0 --port=8080` | 8080 | WebSocket server for realtime broadcast (chat, live order/bid updates). |
| `scheduler` | `umrany_scheduler` | `sh -c "while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done"` | — | Polls the Laravel scheduler every 60s; no built-in cron daemon in the container, so this loop is it. |
| `nginx` | `umrany_nginx` | `nginx:1.27-alpine` reverse proxy | 80 | Routes `/app/*` (the Reverb WebSocket upgrade path) to `reverb`; everything else to the `app` upstream. This is the single entry point clients (web, admin, mobile) actually hit. |
| `postgres` | `umrany_postgres` | `postgres:15-alpine` | 5434→5432 | System of record for all five modules — one schema (`umrany`, not `public`; see `docs/decisions/0006-dedicated-postgres-schema.md`), no per-module DB isolation. Host port is 5434 (not the default 5432) to avoid clashing with a locally-running Postgres on the host; the app talks to it over the internal Docker network at `postgres:5432` regardless — `DB_PORT=5432` in `.env` is the in-network port, not the host-mapped one. Chosen over the source doc's suggested MySQL 8 — see `docs/decisions/0005-postgresql-over-mysql.md`. |
| `redis` | `umrany_redis` | `redis:7.2-alpine` | 6379 | Backs both the Horizon queue driver and the application cache. Accessed via phpredis + igbinary (see `tech-stack.md`). |

`app`, `horizon`, `reverb`, and `scheduler` all depend on `postgres` (health-checked) and `redis` (service-started) before booting. Only `app`, `reverb`, and `nginx` publish ports to the host; `horizon` and `scheduler` are internal-only processes reached through the `app` container (Horizon's dashboard) or not reached directly at all (`scheduler`).

Container names are pinned via `container_name:` in `docker-compose.yml` (all prefixed `umrany_`) rather than left to Compose's auto-generated `<project>-<service>-<index>` naming — this is purely for `docker ps`/`docker logs` readability; `docker compose` commands (`up`, `exec`, `logs`, etc.) still address services by their short name (`app`, `postgres`, ...) regardless. Volumes are similarly pinned to `umrany_postgres_data` and `umrany_redis_data`.

## Scheduled tasks

`routes/console.php` registers whatever runs on the Laravel scheduler, polled every 60s by the
`scheduler` service above. Currently just one entry:

| Command | Schedule | Purpose |
|---|---|---|
| `sanctum:prune-expired --hours=24` | Daily at 03:00 | Deletes expired `personal_access_tokens` rows. `config('sanctum.expiration')` (see `config/sanctum.php`) already makes the Sanctum guard reject an expired token on every request regardless — this only keeps the table from growing forever. Laravel's built-in command handles both `expires_at`-based and global-expiration-based pruning; no custom command was needed. |

## Startup: migrate + seed on every boot

`docker/app/entrypoint.sh` is the image's `ENTRYPOINT`, wrapping whatever `command:` each of the
four services (`app`, `horizon`, `reverb`, `scheduler`) runs. Before exec-ing that command, it
runs `php artisan migrate --force --isolated` then `php artisan db:seed --force`, serialized across
all four services via `flock` on a file in the shared bind-mounted volume (`storage/framework/.boot.lock`)
— whichever container starts first does the work; the other three block on the same lock, then
proceed once it's done. Both steps are safe to run on every boot:

- `migrate --isolated` is a no-op if nothing is pending (`--isolated` also protects against two
  containers racing to run the same pending migration, on top of the `flock` serialization).
- Every seeder in this project **must** be idempotent (`updateOrCreate`/`firstOrCreate`, never bare
  `create()` for anything keyed on a natural key) — this is a hard requirement, not a suggestion,
  since seeders re-run on every `docker compose up`/container restart, not just once. See
  `Modules/Core/database/seeders/*` for the pattern.

If you add a migration that changes a column an existing row could conflict with (a new `NOT NULL`
without a default, a new unique constraint), account for the fact that it may run against a
database that already has real data from a previous boot — same discipline as any production
migration, just triggered more often here than usual.

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

**In practice, prefer a full restart over `octane:reload` when in doubt.** While building the admin
portal, `octane:reload` followed immediately by a request did not reliably pick up new
routes/classes within the same debugging session (workers appeared to still be cycling) — a full
`docker compose restart app` did, every time. If a change genuinely isn't showing up after
`octane:reload`, don't assume the change is wrong before trying a full container restart.

**The admin session guard specifically does not leak across workers** — this was a legitimate
question worth asking (Octane's persistent `AuthManager` singleton gets mutated per-request by
`Auth::shouldUse()`, per the guard resolution mechanism `docs/architecture/admin-portal.md`
describes), and it was verified rather than assumed: `config/octane.php`'s `RequestReceived`
listeners include `FlushAuthenticationState`, which resets that state before every request.

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

## The test suite must run against SQLite `:memory:`, not the real dev database — a Docker-specific `phpunit.xml` gotcha

`phpunit.xml` declares `DB_CONNECTION=sqlite`/`DB_DATABASE=:memory:` (plus `CACHE_STORE=array`,
`SESSION_DRIVER=array`, `APP_ENV=testing`, ...) specifically so the test suite runs isolated from
the real database. **This did not actually work as `<env>` entries, even with `force="true"`, and
the failure mode is silent** — the suite ran, tests passed, and it was quietly hitting the live
`umrany` Postgres database the entire time, discovered only by adding a throwaway test that
dumped `DB::connection()->getDriverName()`/`app()->environment()` mid-run.

**Root cause**: `docker-compose.yml`'s `env_file: .env` on the `app` service injects `.env`'s
values as real OS environment variables inside the container. This app's env-resolution chain
reads `$_SERVER` ahead of `$_ENV`/`getenv()`. PHPUnit's `<env>` directive — `force="true"` included
— only ever touches `$_ENV`/`putenv()`, never `$_SERVER`, so it silently loses to the
Docker-injected value every time. `<server>` entries, by contrast, unconditionally overwrite
`$_SERVER` and actually work.

**`phpunit.xml`'s `<php>` block must therefore use `<server>`, not `<env>`, for every override that
needs to actually take effect in this Docker setup.** If this ever needs revisiting (a PHP/PHPUnit
upgrade, a change to how the container is run outside Docker Compose), reproduce with the same
throwaway-test technique before trusting that `<env>` (with or without `force`) works — don't
assume the standard PHPUnit behavior applies unchanged under `env_file:`-based container env
injection.

One consequence of the suite silently running against the real database for however long that
went unnoticed: `tests/TestCase.php` also did not use `RefreshDatabase`, so a `users`-table-touching
test happened to keep passing (it found the table it needed — because that table was real, not
because the test was correctly isolated). Both are fixed together: `tests/TestCase.php` now
applies `RefreshDatabase` globally, and `phpunit.xml` uses `<server>`. A test suite that never
exercises a genuinely empty, freshly-migrated schema can hide exactly the kind of bug a migration
or a factory default introduces — treat "the suite passes" as meaningless until both of these are
confirmed correct after any change to test bootstrapping.
