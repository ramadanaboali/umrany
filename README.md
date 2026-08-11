# Umrany

Umrany is a construction-tech SaaS platform: a project bidding platform, a construction-materials marketplace, a lightweight ERP, and AI-assisted decision support, sharing one account/subscription/admin system. Full business context: [docs/business/overview.md](docs/business/overview.md).

This repo is the backend API — Laravel 13 as a **modular monolith**, currently a bare skeleton (no business features built yet; see `CLAUDE.md` § Rule 0). Each business capability lives in its own module and can be bought independently of the others.

## Stack

PHP 8.4 · Laravel 13 · Octane (RoadRunner) · PostgreSQL 15 · Redis (phpredis) · Horizon · Reverb (WebSockets) · `nwidart/laravel-modules` · Scribe (OpenAPI docs) · everything runs in Docker. Full rationale per choice: [docs/architecture/tech-stack.md](docs/architecture/tech-stack.md).

## Modules

| Module | Owns | API prefix |
|---|---|---|
| `Modules/Core` | Auth, profiles, provider identity, subscriptions, verification, chat, notifications, wallet/finance, admin/RBAC | `/api/v1/core` |
| `Modules/Projects` | Project bidding: publish → offers → chat → award → completion, tenders | `/api/v1/projects` |
| `Modules/ECommerce` | Multi-vendor construction-materials marketplace | `/api/v1/ecommerce` |
| `Modules/ERP` | Lightweight CRM/quotations/invoices/expenses, independent of Projects | `/api/v1/erp` |
| `Modules/AI` | BOQ analysis, provider matching, offer comparison | `/api/v1/ai` |

Every business module's routes are gated behind `module.entitlement:<alias>` so a customer who only bought, say, ERP never gets access to ECommerce — see [docs/architecture/module-boundaries.md](docs/architecture/module-boundaries.md) § Independent purchasability.

## Quick start

```bash
cp .env.example .env               # if you don't already have a .env
docker compose up -d --build       # first run builds the app image (few minutes)
docker compose exec app php artisan migrate --force
```

Then check it's alive:

```bash
curl http://localhost/api/v1/core/health/ready   # {"status":"ok","checks":{"database":..,"redis":..,"cache":..}}
```

## Where things are

| What | URL |
|---|---|
| API (all `/api/v1/*` traffic, through nginx) | http://localhost |
| API docs — interactive / OpenAPI / Postman | http://localhost/docs · http://localhost/docs.openapi · http://localhost/docs.postman |
| Horizon (queue dashboard) | http://localhost/horizon |
| Log viewer | http://localhost/log-viewer |
| Liveness / readiness health checks | http://localhost/up · http://localhost/api/v1/core/health/ready |
| Reverb (WebSockets) | ws://localhost:8080 |
| PostgreSQL (host-mapped, for a GUI client) | `localhost:5434` |
| Redis (host-mapped) | `localhost:6379` |

## Common commands

```bash
docker compose logs -f app horizon reverb     # tail logs
docker compose exec app composer lint          # Pint (code style)
docker compose exec app composer analyse       # Larastan (static analysis)
docker compose exec app ./vendor/bin/pest       # test suite
docker compose down                            # stop everything (keeps data volumes)
```

## For AI-assisted development

This repo is set up for Claude Code: `CLAUDE.md` is the rules file, `.claude/skills/` has scaffolding workflows (new endpoint, new migration, new job, module-boundary review), and `docs/` (business/architecture/api/modules/decisions) is the source of truth Claude is told to read instead of guessing. See `CLAUDE.md` first if you're working on this with an AI agent.
