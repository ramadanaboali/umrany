---
name: laravel-migration
description: Safe migration workflow for Umrany — module placement, reversibility, indexing, and foreign-key checks. Use whenever adding or changing a database table/column in any module.
---

# Write a safe migration

**Dev-phase exception (root `CLAUDE.md` Rule 7):** this project has no production data yet. If
you're changing a table this project itself introduced (not vendor/package tables), edit the
original migration file directly and reset the database (`migrate:fresh --seed`) instead of writing
a new follow-on migration — the rest of this skill's guidance (reversibility, indexing, FK checks)
still applies in full to genuinely new tables/columns.

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
- Timestamps + soft deletes (`softDeletes()`) by default on any new table backed by its own Eloquent model — root `CLAUDE.md` Rule 9 makes this the default, not the exception. Skip it only for one of the specific excluded shapes that rule lists (append-only/immutable log, singleton config row, a security-sensitive secret/credential whose recoverability would be a regression, or a dependent/owned record with no independent deletion semantics) — and say so in a one-line comment on the model when you skip it, matching the pattern in `Modules/Core/app/Models/{UserProfile,ProviderVerification,SiteSetting,UserMfaSetting,MfaRecoveryCode,VerificationCode,PasswordHistory,NotificationPreference,UserDevice}.php`.
- **Any unique constraint on a soft-deletable table must be a partial unique index excluding trashed rows**, never a plain unique index — `CREATE UNIQUE INDEX ... WHERE deleted_at IS NULL` needs no Postgres/SQLite driver branching for this specific `IS NULL` condition (a boolean-literal partial condition, like a `is_default` singleton flag, does need branching — see `docs/decisions/0020-database-backed-platform-defaults.md`). See `docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md` for the full pattern and the concrete bug this prevents (a soft-deleted row permanently blocking re-registration under the same unique value).

## 4. Running it

```bash
docker compose exec app php artisan module:migrate <Module>      # this module only
docker compose exec app php artisan module:migrate-status
docker compose exec app php artisan module:migrate-rollback <Module>   # verify down() actually works
```

Always run the rollback once locally to prove `down()` is correct before considering the migration done — a migration that only ever runs `up()` in CI is unverified.

## 5. Data-affecting migrations

If the migration needs to backfill or transform existing data (not just add schema), do that in a **separate seeder or one-off command**, not inside the migration's `up()` — migrations should stay schema-only so they stay fast and safe to run in CI/production without side effects.

## 6. Seed it (not optional — root `CLAUDE.md` Rule 6)

A new table backed by its own Eloquent model needs seed data in the same change — master/reference
data goes in a dedicated idempotent seeder method (`updateOrCreate`/`firstOrCreate`), a new
business entity worth demoing extends the module's demo seeder. See root `CLAUDE.md` Rule 6 for
the full checklist. Don't skip this because the migration itself "looks done" — an empty table
that only ever gets rows from manual testing is the gap this rule exists to close.

## 7. Update the docs (not optional — root `CLAUDE.md` Rule 5)

A new table or column is a new (or changed) entity — update, in the same change:

- `Modules/<Module>/CLAUDE.md`'s entity bullet list.
- `docs/modules/<module-alias>.md`'s fuller entity/field description.

If nothing outside the migration file itself changed conceptually (e.g. just adding an index to an already-documented column), no doc update is needed — don't pad docs restating what's already there.
