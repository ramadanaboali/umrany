# Umrany — Rules for Claude

Senior Laravel/PHP engineer on Umrany, a construction-tech SaaS (Projects Hub, Construction E-Commerce,
Project Management, AI layer) — **backend only**: APIs for the public website, user web/mobile apps, and a
separate **Next.js Administration Portal**. Requirements: PRD v1.1 / SRS v1.3 deltas + SRS v1.1 detail + the
lookups seed workbook v1.3 (supersede the v0.1 draft behind `docs/business/*`); delivery order = Trello UMR cards. Trust `docs/` over guessing. Full rationale for every rule below:
[docs/architecture/claude-rules-detail.md](docs/architecture/claude-rules-detail.md) — open the matching section when you need it.

## Rule 0 — build only the active, planned scope
Work only on what the user asked for in this conversation or the **active phase/feature of the
implementation plan** (maintained outside the repo — `CLAUDE.local.md` points to it). No speculative
entities/endpoints/business logic in untouched modules. Infra/tooling/docs work is fine. Unsure → ask.

## Module map (modular monolith, `nwidart/laravel-modules`)
| Module | Owns | API prefix |
|---|---|---|
| `Core` | identity, profiles, providers, company workspace & team, subscriptions & entitlements, payments/wallet ledger, notifications, audit, settings, master data, admin RBAC, CRM, reports engine | `/api/v1/core`, `/api/v1/admin` |
| `Projects` | Projects Hub: projects, offers, chat, BOQ | `/api/v1/projects` |
| `ECommerce` | store, catalog, listings, cart, checkout, supplier orders, delivery OTP, reviews | `/api/v1/ecommerce` |
| `ERP` → **`ProjectManagement`** (rename planned before the first PM card, Sprint 7) | managed projects | `/api/v1/pm` |
| `AI` | AI gateway (vendor integration) + Assistant action layer | `/api/v1/ai` |
| `Content`, `Support` (planned) | CMS/blog/SEO/FAQ/help articles; tickets & support chatbot | `/api/v1/content`, `/api/v1/support` |

Each module has its own `CLAUDE.md` (loads when you work inside it). Admin today = Blade at app root
(`routes/admin.php`, `admin` guard, ADR-0007) — **frozen**: new admin work is JSON Admin API for Next.js.

## Rule 1 — module boundaries
Modules depend only on `Modules/Core` contracts/events; never on another business module's models/namespace.
Run `.claude/skills/module-boundary-check` when touching >1 module.

## Rule 2 — independently purchasable modules
Never gate by `module:enable` status. Business routes live inside the `['auth:sanctum', 'module.entitlement:<alias>']`
group (being replaced by the feature/entitlement/permission-group access pipeline — follow the plan).

## Rule 3 — code conventions
- `declare(strict_types=1)`; `composer lint` (Pint) + `composer analyse` (Larastan) clean.
- **FormRequest → thin Controller → Service → Repository (interface + Eloquent) → API Resource**; DTOs via
  `spatie/laravel-data`, backed enums for states. Caching lives in Services; auth in middleware/policies. ([backend-layering](docs/architecture/backend-layering.md))
- Admin authZ: Policies + spatie permissions on `Admin` only — never `HasRoles` on `User`; end-user capability is computed, never stored.
- **Tests only for critical paths**: money/ledger, multi-transition state machines, access isolation
  (context/entitlement/group/admin permission), OTP/confirmation flows. Otherwise verify via curl. Endpoints need **L5-Swagger** OpenAPI attributes (user/admin docs, `api:docs-coverage`; installed as NFR.19 at the start of UMR-004 — Scramble is removed then).
- Redis keys `umrany:<module>:<entity>:<id>`; Horizon queues `<module>-<priority>`; Reverb `private-<module>.<entity>.<id>`.
- Columns excluded from `#[Fillable]` set via `forceCreate()`/`forceFill()->save()` only.
- Queued **listeners**: `viaQueue()`/`tries()` methods; Jobs/Notifications: `$queue`/`$tries` properties.
- New module → add to `databaseMigrationsPath` + `configDirectories` in `phpstan.neon`.
- Translations in root `lang/{locale}/`; **never create `resources/lang/`**.
- No premature abstractions; no deep inner call chains.

## Rule 4 — Octane persistent workers
No request state in `singleton()` — use `scoped()`. After new classes/routes/config/packages:
`docker compose restart app` (more reliable than `octane:reload`), then `docker compose restart nginx` if 502.
`.env` changes need `docker compose up -d --force-recreate app`; Dockerfile/entrypoint changes need `docker compose build app` first.

## Rule 5 — docs in the same change
Entity/workflow → module `CLAUDE.md` + `docs/modules/<x>.md`; API convention → `docs/api/conventions.md`;
architectural decision → ADR in `docs/decisions/`; cross-module contract → `docs/architecture/module-boundaries.md`.
Also update the external implementation plan (see `CLAUDE.local.md`). Run `docs-sync-check` before "done".

## Rule 6 — seed data with every new model
Idempotent seeders (`updateOrCreate`/`firstOrCreate`) — they run on every container boot. Master data → dedicated seeder;
business entity → extend the demo seeder; admin permissions → `PermissionSeeder`/permission catalog.

## Rule 7 — migrations: clean baseline, then additive-only ([ADR-0034](docs/decisions/0034-lookups-one-table-per-list-from-workbook.md))
Until the first staging deploy (UMR-001; date in ADR-0034): edit the baseline migration in place + `migrate:fresh --seed`.
After it: **additive-only** migrations, never edit a merged migration. Every migration must survive `migrate:fresh` twice and one rollback.

## Rule 8 — engineering bar for every feature
Architecture first; think through the whole workflow (validation, notifications, audit, edge cases); check
entitlement + RBAC + company permission groups on every new surface; decide user API vs Admin API deliberately;
seed + docs are part of done; **run a gap check against the story's ACs before calling a task done**.

## Rule 9 — soft deletes + versioned routes
New table-backed models use `SoftDeletes` (documented exceptions: append-only logs, singleton config, secrets,
owned children). Unique constraints on soft-deletable tables = partial unique index `WHERE deleted_at IS NULL`.
Every API route under `/api/v{n}/<alias>/` (`ApiVersioningTest` enforces it).

## Rule 10 — lists: QueryBuilder + pagination
`QueryBuilder::for(...)->allowedFilters($f1, $f2)` (variadic, not an array) + `->paginate()`, returned as
`Resource::collection($paginator)`. Only bounded picker lists (countries, cities, currencies) may skip pagination — say so explicitly.

## Rule 11 — lookups come from the workbook, never from your keyboard
All lists/statuses/catalogs live in `lkp_*` tables (Core) generated from the lookups workbook export
(`Modules/Core/database/seeders/data/workbook/`, hand edits blocked by a hook). Never hard-code a workbook value or
invent a default for a value the SRS leaves undefined — it stays `value_status=open` and the feature fails closed.
Use the `lookup-table` skill; prove with `core:lookups:verify`.

## Commands (all via Docker)
```bash
docker compose up -d | ps | logs -f app horizon reverb
docker compose exec app composer lint | analyse | reseed | sync-permissions
docker compose exec app php artisan migrate:fresh --seed          # dev only
docker compose exec app ./vendor/bin/pest --filter=<Test>         # critical-path tests
docker compose exec app php artisan core:permissions:audit [--sync]
docker compose exec app php artisan core:admin:promote-super {email} [--revoke]
```
Migrate + seed run automatically on container boot (`docker/app/entrypoint.sh`).

## Skills & agents
Skills: `sprint-loop` (drive a Trello card end-to-end), `next-task` (one planned task), `lookup-table`, `workbook-sync`,
`srs` (requirement lookup), `laravel-endpoint`, `laravel-migration`, `laravel-job`, `module-boundary-check`, `docs-sync-check`.
Agents: `card-planner` (card ACs → plan tasks + PO questions), `srs-analyst` (requirements Q&A), `gap-reviewer`
(pre-approval gap check), `perf-reviewer` (DB/cache), `workbook-auditor` (DB ⇔ workbook), `plan-sync` (update plan docs).
