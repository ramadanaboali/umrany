---
name: laravel-migration
description: Safe migration workflow for Umrany — module placement, reversibility, indexing, and foreign-key checks. Use whenever adding or changing a database table/column in any module.
---

# Write a safe migration

## 1. Placement

Migrations live in the owning module: `Modules/<Module>/database/migrations/`. Generate with:

```bash
docker compose exec app php artisan module:make-migration create_<entities>_table <Module>
```

Never put a migration for module A's tables in module B, even if B is the only current consumer — ownership follows the data, not the caller. If you're unsure which module owns an entity, check `Modules/<Module>/CLAUDE.md` entity lists first.

## 2. Before writing it

- Does an equivalent table/column already exist in `Modules/Core` (e.g. users, wallets, notifications)? Don't duplicate shared concepts — depend on Core's tables via foreign key instead.
- Foreign keys crossing module boundaries are fine at the **database** level (that's just referential integrity) — the boundary rule is about PHP/Eloquent code, not schema. Still, prefer FKs into `Modules/Core` over FKs between two business modules; if a business-module-to-business-module FK feels necessary, that's usually a sign the relationship belongs in Core.

## 3. Writing it

- Always implement `down()` — a migration that can't be rolled back is a landmine for the next engineer. If a `down()` is genuinely destructive (e.g. dropping a column with data), say so in a one-line comment.
- Index every foreign key column and every column used in a `WHERE`/`ORDER BY` in a hot-path query.
- Use `foreignId('...')->constrained()->cascadeOnDelete()` (or `restrictOnDelete()` when cascade would be surprising) rather than bare `unsignedBigInteger`.
- Money columns: `decimal(12, 2)`, never `float`/`double`.
- Timestamps + soft deletes (`softDeletes()`) on anything a user can "delete" but the platform needs for audit/reporting (orders, quotations, invoices — check `docs/architecture/conventions.md` if unsure).

## 4. Running it

```bash
docker compose exec app php artisan module:migrate <Module>      # this module only
docker compose exec app php artisan module:migrate-status
docker compose exec app php artisan module:migrate-rollback <Module>   # verify down() actually works
```

Always run the rollback once locally to prove `down()` is correct before considering the migration done — a migration that only ever runs `up()` in CI is unverified.

## 5. Data-affecting migrations

If the migration needs to backfill or transform existing data (not just add schema), do that in a **separate seeder or one-off command**, not inside the migration's `up()` — migrations should stay schema-only so they stay fast and safe to run in CI/production without side effects.

## 6. Update the docs (not optional — root `CLAUDE.md` Rule 5)

A new table or column is a new (or changed) entity — update, in the same change:

- `Modules/<Module>/CLAUDE.md`'s entity bullet list.
- `docs/modules/<module-alias>.md`'s fuller entity/field description.

If nothing outside the migration file itself changed conceptually (e.g. just adding an index to an already-documented column), no doc update is needed — don't pad docs restating what's already there.
