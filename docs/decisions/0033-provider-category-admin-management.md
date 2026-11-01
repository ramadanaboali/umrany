# 33. Admin management of the provider category/subcategory taxonomy

## Status

Accepted

## Context

`ProviderCategory`/`ProviderSubcategory` (ADRs 0030, 0032) were seed-managed only — no admin
screen existed to add, rename, reorder, or remove a category/subcategory, so changing the taxonomy
meant editing a seeder file and reseeding. The user asked why the admin dashboard couldn't manage
this, and confirmed they want a real admin CRUD screen instead.

## Decisions

- **Full 5-action permission set** (`provider-categories.list/view/create/update/delete`), unlike
  `providers.*`/`users.*`'s 3–4-action asymmetry — a category genuinely has no self-service
  creation path, so the standard 5 (ADR 0009) applies cleanly here, same as `roles.*`/`admins.*`.
- **Subcategory management is gated by the same `provider-categories.*` permissions — no separate
  permission set.** A subcategory is presentationally nested under its category (`GET
  /admin/provider-categories/{category}/subcategories`), the same way Role's permission-checkbox
  management lives inside the Role screen without its own `permissions.*` catalog entries.
- **`Modules\Core\Services\Admin\ProviderCategoryManagementService`** operates directly on the
  Eloquent models (no dedicated Repository) — matching `ProviderManagementService`'s more recent
  precedent rather than `RoleManagementService`'s Repository-backed one; this taxonomy's CRUD has
  no shape complex enough to justify one.
- **A category/subcategory currently assigned to any provider cannot be deleted** — mirrors
  `RoleManagementService::delete()`'s guard against deleting a role still assigned to an admin
  (`role_still_assigned`). Checked via `$category->providers()->exists()` /
  `$subcategory->providers()->exists()`, surfaced as a `ValidationException` (renders through the
  admin layout's existing generic `$errors->any()` flash partial — no new error-display plumbing
  needed).
- **Modal-based create/edit on a single index page**, not dedicated create/edit Blade pages —
  matches the admin Provider screen's edit-modal pattern (`editProviderModal`) rather than Role's
  separate-page pattern; the form here (slug, name_en, name_ar, display_order) is simple enough
  that a page navigation would be pure overhead.
- **Slug uniqueness stays scoped exactly as the schema already enforces it**: globally unique for
  categories, unique *per category* for subcategories (the same subcategory slug may reasonably
  repeat under two different categories) — both soft-delete-aware
  (`whereNull('deleted_at')`/`ignore()`), per ADR 0014.

## Consequences

- The taxonomy can now be edited end-to-end from the dashboard — `ProviderCategorySeeder`/
  `ProviderSubcategorySeeder` remain the *initial* content source (and stay the source of truth
  for a fresh environment/reseed), but are no longer the only way to change it going forward.
- `docs/architecture/admin-portal.md` gained a "Provider categories screen" section; the sidebar
  gained a "Categories" entry under `provider-categories.list`.
