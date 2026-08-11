# 5. PostgreSQL over MySQL

## Status

Accepted

## Context

The source requirements doc (Chapter 6, Technical Architecture) suggested MySQL 8 as the database, and the platform was bootstrapped on MySQL 8 initially. The team then requested PostgreSQL instead. Both are viable relational stores for this workload (a single shared schema across five modules, no exotic MySQL-only or Postgres-only feature currently in use), so this is primarily a team-preference decision rather than one forced by a specific technical requirement uncovered so far — nothing in the current schema (Sanctum tokens, spatie/permission tables, the module skeletons) depends on MySQL-specific behavior.

## Decision

Run PostgreSQL 15 as the system of record, replacing MySQL 8 in `docker-compose.yml` (service renamed `mysql` → `postgres`, image `postgres:15-alpine` — pinned to 15 rather than the newer 16 because 15-alpine was already cached locally and the environment's Docker Hub access goes through a TLS-inspecting proxy that broke pulling new image layers; revisit the pin once that's resolved) and in the app image (`docker/app/Dockerfile` now compiles `pdo_pgsql`/`pgsql` instead of `pdo_mysql`). `.env`/`.env.example` set `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432`; the MySQL-specific `DB_ROOT_PASSWORD` var is dropped since Postgres's official image grants superuser to `POSTGRES_USER` directly.

## Consequences

- This was a same-day switch on a greenfield database with no real data — the old `umrany-mysql` Docker volume was discarded and migrations re-run fresh against Postgres. There was no data migration to perform.
- The `postgres` service publishes host port **5434**, not Postgres's default 5432, since a developer machine may already run its own local Postgres on 5432 — `docker-compose.yml` maps `5434:5432`. This is host-side only; inside the Docker network the app still reaches it at `postgres:5432`, which is what `.env`'s `DB_PORT=5432` refers to. Connect from host tools (e.g. a GUI client) via `localhost:5434`.
- Laravel's query builder/Eloquent abstracts most dialect differences, but future migrations should avoid MySQL-only column types or SQL (e.g. `ENUM` columns behave differently; prefer a `string` + validation, or a Postgres-native enum type if genuinely needed) and double-check any raw SQL for Postgres compatibility.
- Case-sensitivity and identifier-quoting rules differ from MySQL (Postgres lower-cases unquoted identifiers and is case-sensitive on quoted ones) — shouldn't matter under Eloquent's normal usage, but is worth knowing if writing raw queries.
- If a genuine Postgres-specific advantage becomes relevant later (e.g. native JSONB indexing/querying for BOQ line-item data in `Modules/AI`, or array/range column types), that's a legitimate reason to lean into Postgres-specific features rather than staying MySQL-portable — this decision doesn't mandate cross-database portability going forward.
