# Umrany — Rules for Claude

You are acting as a senior Laravel/PHP engineer on Umrany, a construction-tech SaaS platform. This file is the rules Claude Code must follow in this repo. For *why* the product works this way, read `docs/` rather than asking or guessing — it is kept current on purpose.

## Rule 0 — this is a bare modular skeleton, not a feature build

This app is intentionally infrastructure-only right now: 5 empty (or near-empty) modules, auth/permission tables, health checks, and the docs/conventions to build on top of. **Do not add business features (real entities, CRUD endpoints, business logic) unless the user explicitly asks for that specific feature in that conversation.** Infrastructure/tooling work (health checks, logging, CI, docs, migrations for framework-level tables) is fine; inventing product functionality speculatively is not. If you're unsure whether something counts as "a feature," ask.

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

## Rule 1 — module boundaries (Core-only dependency)

A module may depend on `Modules/Core` contracts/events. **Two business modules never reach into each other's Eloquent models directly.** Cross-module communication goes through `Modules/Core/app/Events` (domain events) or an explicit `Contracts` interface the owning module binds in its service provider. If you're about to `use Modules\ECommerce\...` from inside `Modules/ERP`, stop — that's a boundary violation; run `.claude/skills/module-boundary-check` before you open a PR.

## Rule 2 — modules must stay independently purchasable

A customer can buy ERP without ECommerce, ECommerce without Projects, etc. **Never gate a feature by checking `module:disable`/`module:enable` status** — that's application-wide, not per-customer. Instead, every business module's route group is wrapped in `['auth:sanctum', 'module.entitlement:<alias>']`, which asks `Modules\Core\Contracts\ModuleEntitlementChecker` (bound to `SubscriptionEntitlementChecker` in `CoreServiceProvider`) whether the current user may use that module. New business-module routes must go inside that already-gated group, not around it. See [docs/architecture/module-boundaries.md](docs/architecture/module-boundaries.md) § Independent purchasability.

## Rule 3 — coding conventions

- `declare(strict_types=1)` in every new PHP file.
- Style: `composer lint` (Pint) before committing. Static analysis: `composer analyse` (Larastan) must be clean.
- Controllers stay thin: **FormRequest → Action class → API Resource**. No business logic in controllers, no validation logic outside FormRequests.
- Authorization via Policies + `spatie/laravel-permission` roles/permissions. Never `if ($user->role === 'admin')`.
- Every new endpoint needs: a Pest feature test, and Scribe-compatible doc-blocks (`@group`, `@bodyParam`, `@response`) so `/docs` stays accurate — see [docs/api/conventions.md](docs/api/conventions.md).
- Redis cache keys: `umrany:<module>:<entity>:<id>`. Horizon queues: `<module>-<priority>` (e.g. `ecommerce-high`, `ai-low`). Reverb channels: `private-<module>.<entity>.<id>`.
- Don't build abstractions the current task doesn't need. Three similar lines beat a premature interface.

## Rule 4 — the Octane persistent-worker gotcha

Octane workers stay booted across requests **and across a `composer require`/new-file-add until the container restarts** — this bit us during development: adding a new package or a new class doesn't take effect in an already-running worker.

- Never put request/container state into a `singleton()` binding — use `scoped()` for anything per-request.
- After adding routes/classes/config or requiring a new package while the stack is already running: `docker compose exec app php artisan octane:reload` first; if that errors or the container already crashed, `docker compose up -d app horizon reverb scheduler` to get fresh processes, then `docker compose restart nginx` (nginx caches the app container's IP and won't reconnect after a recreate on its own).
- Enabling/disabling a module or changing `config/modules.php` requires the same reload — not just `cache:clear`.

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

## Commands (everything runs through Docker)

```bash
docker compose up -d                                    # start the full stack
docker compose exec app php artisan module:make Name    # scaffold a 6th module (rare)
docker compose exec app php artisan module:make-controller Name Module
docker compose exec app composer lint                    # Pint
docker compose exec app composer analyse                 # Larastan
docker compose exec app ./vendor/bin/pest                # tests (includes Modules/*/tests)
docker compose exec app php artisan horizon:status
docker compose exec app php artisan scribe:generate       # regenerate /docs after route/doc-block changes
docker compose logs -f app horizon reverb
```

## Where to look next

- New business context or a requirement you're unsure about → `docs/business/`
- What order to build things in, and why → `docs/business/roadmap.md`
- "Why is it structured this way" → `docs/architecture/`
- Request/response shape, auth, versioning, health-check endpoints → `docs/api/conventions.md`
- Entity list and workflows for the module you're touching → `Modules/<Name>/CLAUDE.md`
- Decisions already made and why → `docs/decisions/`

## Skills

- `.claude/skills/laravel-endpoint` — scaffold a new API endpoint (Controller + FormRequest + Resource + route + Scribe doc-block + Pest test) the way this project expects.
- `.claude/skills/laravel-migration` — safe migration workflow (reversibility, indexes, FKs, module placement).
- `.claude/skills/laravel-job` — queued Job wired to the correct Horizon supervisor/queue for its module.
- `.claude/skills/module-boundary-check` — pre-merge check for cross-module Eloquent/namespace leaks.
- `.claude/skills/docs-sync-check` — pre-merge check that `docs/`/`CLAUDE.md` files were actually updated to match what the code changed (Rule 5). Run it before considering any non-trivial change done.
