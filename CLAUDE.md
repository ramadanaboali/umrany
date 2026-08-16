# Umrany — Rules for Claude

You are acting as a senior Laravel/PHP engineer on Umrany, a construction-tech SaaS platform. This file is the rules Claude Code must follow in this repo. For *why* the product works this way, read `docs/` rather than asking or guessing — it is kept current on purpose.

## Rule 0 — build only what's been explicitly asked for, phase by phase

Per `docs/business/roadmap.md`, Phase 1 (Auth/Profile/Provider identity+verification) and a
pulled-forward slice of Phase 7 (Admin/RBAC + the admin Blade dashboard) are now built in
`Modules/Core` and root `app/` — see that module's `CLAUDE.md` "Implementation status" line for
exactly what exists. `Modules/Projects`, `Modules/ECommerce`, `Modules/ERP`, `Modules/AI`, and
Core's Subscription/Chat/Notification/Finance/Reports/CMS sub-areas are still bare skeletons.
**Do not add business features (real entities, CRUD endpoints, business logic) to any of those
unless the user explicitly asks for that specific feature in that conversation.**
Infrastructure/tooling work (health checks, logging, CI, docs, migrations for framework-level
tables) is fine; inventing product functionality speculatively is not. If you're unsure whether
something counts as "a feature," ask.

## Product in one breath

Umrany connects construction project owners, contractors/suppliers, and platform admins through four products — a project bidding platform, a construction materials marketplace, a lightweight ERP, and AI-assisted decision support — sharing one account, one subscription system, and one admin portal. Full detail: [docs/business/overview.md](docs/business/overview.md), [docs/business/business-model.md](docs/business/business-model.md).

## Module map

This is a modular monolith (`nwidart/laravel-modules`). Full rationale and dependency rules: [docs/architecture/module-boundaries.md](docs/architecture/module-boundaries.md).

| Module | Owns | API prefix |
|---|---|---|
| `Modules/Core` | Auth, profiles, provider identity, subscriptions, verification, chat, notifications, wallet/finance primitives, reporting engine, admin/RBAC | `/api/v1/core` |
| `Modules/Projects` | Project bidding platform: publish → offers → chat → award → completion, tenders | `/api/v1/projects` |
| `Modules/ECommerce` | Multi-vendor marketplace: stores, products, cart, checkout, orders, delivery, supplier/customer wallets, reviews | `/api/v1/ecommerce` |
| `Modules/ERP` | Lightweight CRM, quotations, invoices, expenses — independent of the Projects module by design | `/api/v1/erp` |
| `Modules/AI` | BOQ analysis, provider matching, offer comparison — exposes service contracts other modules call | `/api/v1/ai` |

Each module has its own `CLAUDE.md` with its entity list, workflows, and gotchas — it loads automatically when you're working inside that directory.

**The one exception to "everything lives in `Modules/*`":** the admin dashboard
(`app/Http/Controllers/Admin`, `resources/views/admin`, `routes/admin.php`) lives at the
application root, session-authenticated via a separate `admin` guard — see
[docs/architecture/admin-portal.md](docs/architecture/admin-portal.md) and
[docs/decisions/0007-in-monolith-blade-admin.md](docs/decisions/0007-in-monolith-blade-admin.md)
for why. Its data/RBAC model (`Admin`, roles, permissions) is still Core-owned; only the UI layer
sits outside the module tree, and it still only depends on Core the way every module does.

## Rule 1 — module boundaries (Core-only dependency)

A module may depend on `Modules/Core` contracts/events. **Two business modules never reach into each other's Eloquent models directly.** Cross-module communication goes through `Modules/Core/app/Events` (domain events) or an explicit `Contracts` interface the owning module binds in its service provider. If you're about to `use Modules\ECommerce\...` from inside `Modules/ERP`, stop — that's a boundary violation; run `.claude/skills/module-boundary-check` before you open a PR.

## Rule 2 — modules must stay independently purchasable

A customer can buy ERP without ECommerce, ECommerce without Projects, etc. **Never gate a feature by checking `module:disable`/`module:enable` status** — that's application-wide, not per-customer. Instead, every business module's route group is wrapped in `['auth:sanctum', 'module.entitlement:<alias>']`, which asks `Modules\Core\Contracts\ModuleEntitlementChecker` (bound to `SubscriptionEntitlementChecker` in `CoreServiceProvider`) whether the current user may use that module. New business-module routes must go inside that already-gated group, not around it. See [docs/architecture/module-boundaries.md](docs/architecture/module-boundaries.md) § Independent purchasability.

## Rule 3 — coding conventions

- `declare(strict_types=1)` in every new PHP file.
- Style: `composer lint` (Pint) before committing. Static analysis: `composer analyse` (Larastan) must be clean.
- Controllers stay thin: **FormRequest → Service (calling a Repository) → API Resource**. No business logic in controllers, no validation logic outside FormRequests, no inline Eloquent queries in a Service for anything a Repository already owns. Full layering rationale (Gateway/Orchestration/Service/Repository, where caching and auth each belong): [docs/architecture/backend-layering.md](docs/architecture/backend-layering.md).
- Authorization via Policies + `spatie/laravel-permission` roles/permissions. Never `if ($user->role === 'admin')`. `spatie/laravel-permission`'s `HasRoles` is reserved for `Modules\Core\Models\Admin` (the `admin` guard) only — never add it to `App\Models\User`. End-user capability (Project Owner/Supplier/ERP User) is computed, never stored — see [docs/architecture/module-boundaries.md](docs/architecture/module-boundaries.md) § User capability resolution.
- Every new endpoint needs: a Pest feature test, and Scribe-compatible doc-blocks (`@group`, `@bodyParam`, `@response`) so `/docs` stays accurate — see [docs/api/conventions.md](docs/api/conventions.md).
- Redis cache keys: `umrany:<module>:<entity>:<id>`. Horizon queues: `<module>-<priority>` (e.g. `ecommerce-high`, `ai-low`). Reverb channels: `private-<module>.<entity>.<id>`.
- Don't build abstractions the current task doesn't need. Three similar lines beat a premature interface.
- A trusted `Service`/`Repository` class setting a column deliberately excluded from a model's `#[Fillable(...)]` (e.g. `status`, `is_super_admin`, a foreign key the client must never set directly) must use `Model::forceCreate()`/`$model->forceFill(...)->save()`, never `create()`/`fill()` — the latter silently drops the value with no error. Doesn't apply inside `database/factories/*`; Eloquent factories bypass the guard internally.
- Queued **listeners** (`implements ShouldQueue` on a class handling an event) use a different wiring convention than Jobs/Notifications: the queue name comes from a `viaQueue(): string` **method**, retry count from a `tries(): int` method — a plain `public $queue`/`$tries` property is silently ignored. Notifications and Jobs *do* use plain properties (`public $queue`, `public int $tries`) via their own `Queueable` trait — don't cross the two conventions.
- A new module added to `Modules/*` must be added to **both** `databaseMigrationsPath` and `configDirectories` in `phpstan.neon`, or every new column/config value in that module's own `database/migrations`/`config` reads as a false-positive Larastan error unrelated to the actual code.

## Rule 4 — the Octane persistent-worker gotcha

Octane workers stay booted across requests **and across a `composer require`/new-file-add until the container restarts** — this bit us during development: adding a new package or a new class doesn't take effect in an already-running worker.

- Never put request/container state into a `singleton()` binding — use `scoped()` for anything per-request.
- After adding routes/classes/config or requiring a new package while the stack is already running: `docker compose exec app php artisan octane:reload` first; if that errors or the container already crashed, `docker compose up -d app horizon reverb scheduler` to get fresh processes, then `docker compose restart nginx` (nginx caches the app container's IP and won't reconnect after a recreate on its own).
- Enabling/disabling a module or changing `config/modules.php` requires the same reload — not just `cache:clear`.
- In practice, `octane:reload` has not reliably picked up new routes/classes within the same debugging session — a full `docker compose restart app` has, every time. Don't spend long debugging "why isn't my change showing up" before trying a full restart.
- The `admin` session guard's per-request `Auth::shouldUse()` mutation does **not** leak across requests in the same worker — `config/octane.php`'s `FlushAuthenticationState` listener resets it. Verified directly during implementation, not assumed; see `docs/architecture/admin-portal.md`.
- **`docker compose restart app` is NOT enough after editing `.env`.** Code/route/class changes are read fresh from the bind-mounted volume on any process restart, but `env_file: .env` values are injected only at container *creation*, not on `restart`. A changed `.env` value (confirmed directly: a corrected `MAIL_MAILER`/`MAIL_HOST` still resolved to the old value after `restart`) needs `docker compose up -d --force-recreate app` (or `up -d` after the compose file itself changed) to actually take effect.

See [docs/architecture/infrastructure.md](docs/architecture/infrastructure.md).

## Rule 5 — docs and CLAUDE.md files stay in sync with the code, in the same change

Documentation in this repo is treated as load-bearing, not optional extra credit — a future Claude session (or engineer) is told to trust `docs/` over guessing, so stale docs actively mislead. **When you change something docs describe, update the doc in the same turn you make the change, not "later."** Concretely:

- New/changed entity, table, or workflow in a module → update that `Modules/<Name>/CLAUDE.md`'s entity list/workflow section **and** the fuller `docs/modules/<name>.md`.
- New/changed API convention (auth, envelope shape, versioning, error format) → `docs/api/conventions.md`.
- New/changed architectural decision (a package swap, a new service, an infra change) → add or update a `docs/decisions/NNNN-*.md` ADR, and touch `docs/architecture/*.md` if the topology/tech-stack tables describe it.
- New/changed cross-module contract, event, or the entitlement pattern → `docs/architecture/module-boundaries.md`.
- New skill-worthy repeatable workflow → consider whether `.claude/skills/` needs a new or updated `SKILL.md`, and whether this file's **Skills** list below needs the new entry.
- Business-facing change (revenue model, product scope, personas) → `docs/business/*.md`.

If you're not sure a doc needs updating, err toward checking `docs/` for anything that mentions what you just touched (`grep -rl <keyword> docs/ Modules/*/CLAUDE.md`) rather than skipping the check. Before considering a task "done," run `.claude/skills/docs-sync-check` on what you changed.

## Rule 6 — every new Eloquent model gets seed data in the same change

Whenever you add a new model backed by its own table (not a pivot), add or extend that module's seeder in the same change — don't leave a model with zero rows producible outside manual testing. Concretely:

- **Master/reference data** (a fixed or slowly-changing catalog — countries, currencies, categories, permissions) → a dedicated seeder method/class, idempotent (`updateOrCreate`/`firstOrCreate` keyed on a natural key), called from that module's `<Module>DatabaseSeeder`.
- **A new business entity that needs a realistic example to exercise the feature** (a new Provider sub-entity, a new order status, ...) → extend `DemoUserSeeder` (or the equivalent demo seeder once other modules have one) so the seeded demo account actually exercises the new entity, not just the schema.
- Every seeder must stay idempotent — it runs on every container boot (`docker/app/entrypoint.sh`, see `docs/architecture/infrastructure.md` § Startup), not once. `create()` is wrong here; `updateOrCreate`/`firstOrCreate` is right.
- If the new model needs its own permission(s) to be admin-manageable, add them to `PermissionSeeder` in the same change, not speculatively ahead of the screen that uses them (Rule 0).
- `.claude/skills/laravel-migration` already covers migration mechanics; treat "does this need a seeder" as a standing checklist item every time that skill runs, not a separate ask.

## Rule 7 — dev-phase migration policy: edit in place, then reset the database

This project has no production deployment and no real user data yet — every environment's database
is disposable. Until the user says otherwise (e.g. after a real production launch), schema changes
to a table this project itself introduced don't need a new incremental follow-on migration for
every tweak:

- **Edit the original migration file directly** (the one that created or last meaningfully changed
  the table) instead of layering another `ALTER TABLE`-style migration on top of it. Keep the
  table's schema defined in one place, the way it would have looked if written correctly the first
  time.
- After editing, **drop and recreate** the affected database so the edited migration runs cleanly:
  `docker compose exec app php artisan migrate:fresh --seed` (whole dev DB) is the normal move.
  This is destructive (drops all data in that environment) — same as any other destructive action,
  don't run it against an environment that might hold real work without checking first.
- **Data remaps also don't need a one-time migration** — if a change (like a permission catalog
  reshape) would otherwise need a `Permission::findOrCreate`/data-transform migration to preserve
  already-seeded rows, just edit the seeder/config and reseed from scratch instead; idempotent
  seeders already reproduce the target state from their single source of truth.
- This does **not** relax `.claude/skills/laravel-migration`'s normal safety guidance (`down()`,
  indexing, FK checks) for genuinely new tables/columns — it only means: don't feel obliged to
  write a careful, non-destructive follow-on migration just to adjust a table this same project
  added a few migrations ago with no real data behind it.
- The moment any environment holds data worth preserving across a migration change, this rule
  stops applying there — revert to normal additive/reversible migration practice for that
  environment going forward.

## Commands (everything runs through Docker)

```bash
docker compose up -d                                    # start the full stack
docker compose exec app php artisan module:make Name    # scaffold a 6th module (rare)
docker compose exec app php artisan module:make-controller Name Module
docker compose exec app composer lint                    # Pint
docker compose exec app composer analyse                 # Larastan
docker compose exec app ./vendor/bin/pest                # tests (includes Modules/*/tests) — runs isolated sqlite :memory:, see phpunit.xml
docker compose exec app composer reseed                  # re-run every seeder (idempotent — safe anytime, doesn't drop data)
docker compose exec app composer sync-permissions        # full admin RBAC rebuild from Modules/Core/config/permissions.php — preserves dashboard-created roles + admin assignments, see SyncPermissionsCommand
docker compose exec app php artisan core:sync-permissions --attach=<perm> --role=<role>   # ad-hoc attach a permission to a role from the CLI (or --detach); flushes Spatie's permission cache
docker compose exec app php artisan core:permissions:audit [--sync]   # diff can:<permission> route middleware against the catalog/database; --sync creates any permission a route uses but the DB lacks
docker compose exec app php artisan core:admin:promote-super {email} [--revoke]   # the only way to grant/revoke is_super_admin — never settable via the dashboard/API
docker compose exec app php artisan horizon:status
docker compose exec app php artisan scribe:generate       # regenerate /docs after route/doc-block changes
docker compose logs -f app horizon reverb
```

Migrate+seed also run automatically on every `app`/`horizon`/`reverb`/`scheduler` container boot (`docker/app/entrypoint.sh`) — see [docs/architecture/infrastructure.md](docs/architecture/infrastructure.md) § Startup. Every seeder must therefore be idempotent (`updateOrCreate`/`firstOrCreate`), not just safe to run once.

## Where to look next

- New business context or a requirement you're unsure about → `docs/business/`
- What order to build things in, and why → `docs/business/roadmap.md`
- "Why is it structured this way" → `docs/architecture/`
- Request/response shape, auth, versioning, health-check endpoints → `docs/api/conventions.md`
- Entity list and workflows for the module you're touching → `Modules/<Name>/CLAUDE.md`
- Decisions already made and why → `docs/decisions/`
- Admin dashboard structure, auth, RBAC → `docs/architecture/admin-portal.md`

## Skills

- `.claude/skills/laravel-endpoint` — scaffold a new API endpoint (Controller + FormRequest + Resource + route + Scribe doc-block + Pest test) the way this project expects.
- `.claude/skills/laravel-migration` — safe migration workflow (reversibility, indexes, FKs, module placement).
- `.claude/skills/laravel-job` — queued Job wired to the correct Horizon supervisor/queue for its module.
- `.claude/skills/module-boundary-check` — pre-merge check for cross-module Eloquent/namespace leaks.
- `.claude/skills/docs-sync-check` — pre-merge check that `docs/`/`CLAUDE.md` files were actually updated to match what the code changed (Rule 5). Run it before considering any non-trivial change done.
