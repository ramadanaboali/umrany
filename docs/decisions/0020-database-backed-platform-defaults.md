# 20. Country/currency "default" resolution moves from config to a DB flag

## Status

Accepted

## Context

`Country::default()`/`Currency::default()` (used, among other places, to give a new registration a
sensible starting country/currency) were a hardcoded config-code lookup — `config('core.defaults.
country_code') === 'SA'` compared against every row until one matched. That works, but it means
the platform-wide default can only change via a code/env deploy, never through data — and it
silently assumes exactly one row will ever match, with nothing enforcing that at the database
level.

## Decision

- Added `is_default` boolean to both `countries` and `currencies` (migrations edited in place per
  root `CLAUDE.md` Rule 7), each with a partial unique index enforcing that **at most one row per
  table** may have `is_default = true`:
  ```sql
  -- Postgres
  CREATE UNIQUE INDEX countries_single_default ON countries ((is_default)) WHERE is_default;
  -- SQLite
  CREATE UNIQUE INDEX countries_single_default ON countries (is_default) WHERE is_default = 1;
  ```
  Unlike ADR 0014's `WHERE deleted_at IS NULL` partial indexes, a boolean-literal partial condition
  **does** need driver branching — Postgres accepts a bare boolean column reference as a partial
  index predicate, SQLite requires the explicit `= 1` comparison.
- `Country::default()`/`Currency::default()` now query `where('is_default', true)->first()`
  instead of comparing against config.
- `config('core.defaults.country_code'/'currency_code')` is **not removed** — it becomes the seed
  source only. `MasterDataSeeder` sets each row's `is_default` flag by comparing its code against
  those config values, so a fresh install's behavior is unchanged from before this ADR. Runtime
  code never reads that config directly anymore.

## Consequences

- The platform-wide default country/currency can now change via a data update (an admin screen,
  were one ever built) without a code deploy — though no such screen exists yet (root `CLAUDE.md`
  Rule 0; flagged here as the natural next step, not built speculatively).
- The single-default invariant is enforced by the database itself, not just by seeder discipline —
  a second row accidentally set `is_default = true` (by any future code path, seeder or otherwise)
  fails loudly with a constraint violation instead of silently producing an ambiguous "which one is
  the real default" state.
- Anyone adding a similar single-row-flag pattern elsewhere in this codebase should check whether
  their condition is a plain `IS NULL`-style check (no branching needed, see ADR 0014) or a boolean
  literal (branching needed, as here) before copying either pattern verbatim.
