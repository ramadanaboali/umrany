# ADR-0034 — Lookups: one `lkp_` table per list, generated from the seed workbook; clean migration baseline

- **Status:** Accepted (2026-11-17, product owner) — Trello UMR-002
- **Supersedes:** the master-data parts of ADR-0032 (provider subcategories as a separate table) and ADR-0033 (provider category admin model); the `lookup_lists`/`lookup_items` idea in the implementation plan (ADM.8).

## Context
The client-facing *Lookups Seed Data* workbook v1.3 (24 sheets, synced to SRS v1.3) is the data reference for every
list, status model, catalog and configurable setting. UMR-002 requires all 24 sheets to be represented in schema or seed
data, **each value traceable to its workbook source**, values the SRS leaves undefined stored as **open configuration**,
and versioned, repeatable migrations across environments.

## Decision
1. **One table per lookup** (≈146): Admin–Master Data, System-Enum lists, Config lists, each status model, each
   Change-Request list — all in `Modules/Core`, prefix `lkp_` (`lkp_task_priorities`, `lkp_status_hub_projects`,
   `lkp_cr_property_types`). Shared columns via a Blueprint macro + abstract `LookupModel`.
2. Values are **generated, never typed**: `wb_export.py` (implementation-plan `.tools/workbook/`) exports the workbook to
   `Modules/Core/database/seeders/data/workbook/v<ver>/` (JSON + `registry.json`); seeders read it; every row stores
   `wb_sheet` + `wb_row`; `core:lookups:verify` proves DB ⇔ workbook ⇔ PHP enums and runs in CI.
3. Seed workbook as-is (SRS + Proposed values with the workbook's Active flag); CR lists seeded **inactive**.
4. References: stable **code** (string FK + backed enum) for system/status/config values; **id** FK for admin master data.
5. Names: `name_en` / `name_ar`. Regions: `lkp_regions` with nullable `lkp_cities.region_id`. Category trees: one
   self-referencing table each (`parent_id`, `level`); `provider_subcategories` removed.
6. Status transitions: code (state enums) is authoritative; the workbook sheet is seeded verbatim as reference and CI
   checks code ↔ table through a reviewed mapping.
7. Open values: `value = null`, `value_status = open`, `open_decision_code` (FK to `lkp_open_decisions`); consumers fail closed.
8. **Migrations:** all existing migrations are rewritten into a clean, guard-free baseline now (no staging/production data
   exists). From the **first staging deploy** (date recorded below) migrations are additive-only and the dev-phase
   "edit in place" rule (CLAUDE.md Rule 7) is retired.

## Consequences
- + 100 % traceable seed data; client answers / new workbook versions become data changes; AC2/AC3/AC4 verifiable by command.
- + Strong per-domain FKs and enums.
- − Many tables/models (generated, so cost is review not typing); admin UI (UMR-064) needs a registry-driven generic CRUD.
- − Existing demo data (UAE/Egypt, old provider category slugs) is replaced by workbook codes.

## Cut-over log
| Event | Date |
|---|---|
| Clean baseline merged | _pending_ |
| First staging deploy → additive-only migrations | _pending_ |
