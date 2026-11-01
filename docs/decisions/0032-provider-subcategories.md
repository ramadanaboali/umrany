# 32. Provider subcategories, seeded and optional

## Status

Accepted

## Context

`docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md` deliberately deferred
`ProviderSubcategory` — the source spec pairs a subcategory list with each of the 10 named
categories but never enumerates one, so there was no real content to seed. The user has now
explicitly directed building it anyway: seed a subcategory taxonomy the same way the 10 top-level
categories were seeded, and make a provider's subcategory selection nullable/optional (unlike
categories, which require at least one).

## Decisions

- **`ProviderSubcategory` is a real table**, nested under `ProviderCategory` (`provider_category_id`
  FK), bilingual (`name_en`/`name_ar`), soft-deletable, slug-unique **per category** (not
  globally — two different categories may reasonably share a subcategory slug/name).
- **The seeded content (34 subcategories across the 10 categories,
  `ProviderSubcategorySeeder`) is a synthetic, reasonable construction-industry taxonomy — not
  sourced from the spec**, the same transparency standard as every other place this codebase has
  had to invent structure the spec didn't provide. Flagging this explicitly rather than letting it
  read as spec-derived.
- **Selecting subcategories is always optional** — `subcategory_ids` is `nullable`/`sometimes` on
  both activate and update, with no `min:1`, unlike `category_ids`. An empty array on update
  clears the selection entirely.
- **A selected subcategory must belong to one of the selected categories** — enforced in both
  `ActivateProviderRequest` and `UpdateProviderRequest` via an `after()` closure, not left as a
  data-integrity gap. On update, when `category_ids` isn't part of that particular request, the
  check falls back to the provider's current category set rather than assuming the request is
  changing categories too.
- **Public API surface mirrors `ProviderCategory` exactly**: `GET /provider-subcategories`
  (unpaginated bounded picker, Rule 10's named exception, `filter[provider_category_id]` exact +
  `filter[search]`), exposed on `ProviderResource` as `subcategories` (both languages returned, no
  server-side locale resolution — same as categories), and addable to the public directory filter
  (`GET /providers?filter[subcategory]=<id>`) alongside the existing `filter[category]`.
- **No admin CRUD screen for the taxonomy itself** — same as `ProviderCategory`, this is
  seed-managed master data, not something an admin edits through the dashboard today. The admin
  provider show page displays a provider's selected subcategories as read-only badges, same
  treatment as categories there.

## Consequences

- `docs/decisions/0030`'s "No ProviderSubcategory in this phase" line is superseded by this ADR —
  `Modules/Core/CLAUDE.md`'s entity list and `docs/modules/core.md` § Provider are updated to
  match, rather than leaving two ADRs in silent conflict.
- Because the seeded taxonomy is synthetic, it's expected to be revised (renamed, reorganized, or
  replaced wholesale) once real product/business input on the actual desired subcategory list
  exists — `ProviderSubcategorySeeder`'s `updateOrCreate`-by-`(category, slug)` shape makes that a
  safe, idempotent edit-and-reseed, not a migration.
