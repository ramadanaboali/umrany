---
name: workbook-sync
description: Re-import the UMRANY lookups seed workbook when SYNTECH/the client sends a new version or answers client questions — export, diff against the committed export, regenerate seeders/tables, verify until zero mismatches, and record the business changes in the plan. Use when a new "UMRANY_Lookups_Seed_Data_v*.xlsx" appears or the user says lookups/answers changed.
---

# Sync a new workbook version

Tool: `/usr/bin/python3 -I /Users/ramadan/Umrany/implementation-plan/.tools/workbook/wb_export.py <xlsx> <out_dir> [--version X.Y] [--check]`
(use `/usr/bin/python3` — Homebrew python's expat is broken on this machine).

1. **Stale?** `wb_export.py <xlsx> Modules/Core/database/seeders/data/workbook/v1_3 --check`. OK → nothing to do.
2. **Export to a new folder** (never overwrite an imported version): `.../workbook/v<major>_<minor>/`.
3. **Diff** old vs new: `diff <(jq -S . old/registry.json) <(jq -S . new/registry.json)` and per changed sheet
   compare rows by code (added / removed / renamed label / active flag / source / notes). Write the diff
   summary (counts + every changed code) — this is the business change list.
4. **Classify** each change: label/flag/answer only (data) · new lookup or new column (schema → new
   *additive* migration after staging cut-over) · removed value (deactivate, never delete) · conflicts with a
   decision (D-LKP-*, PLAN-D-*) → **stop and ask the user**.
5. Point the seeders/registry config at the new folder; run the `lookup-table` verify loop (max 5 rounds) and
   the `workbook-auditor` agent on `all`.
6. **Plan:** `plan-sync` agent with: changelog row (business), affected F-LKP tasks / cards, answered
   Client Questions → `lkp_client_questions` + `open-decisions.md` status, `🔁` on affected stories.
7. Tell the user: version, sha256, counts changed, any decision conflicts, cards affected.
