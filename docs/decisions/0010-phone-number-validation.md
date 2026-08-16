# 10. Phone/mobile numbers are validated against Saudi or Egyptian formats

## Status

Accepted

## Context

`Admin.phone` (root `app/Http/Requests/Admin/*`) and `User.mobile` (`Modules/Core/app/Http/
Requests/{Auth/RegisterRequest,Profile/UpdateProfileRequest}.php`) had no format validation at
all — just `string`+`max:N`, with no shared Rule class, no regex, and no phone-validation package
in `composer.json`. The platform currently serves the Saudi and Egyptian markets, and needed real
format enforcement on both fields without inventing two separate validation stories for what is
conceptually the same kind of data on two different models.

## Decision

- One reusable rule, `Modules\Core\Rules\SaudiOrEgyptianPhoneNumber`, implementing
  `Illuminate\Contracts\Validation\ValidationRule` — accepts a Saudi mobile number (`05XXXXXXXX`
  locally, `+9665XXXXXXXX`/`009665XXXXXXXX` internationally) or an Egyptian one
  (`01[0125]XXXXXXXX` locally, `+201[0125]XXXXXXXX`/`00201[0125]XXXXXXXX` internationally), after
  stripping whitespace/dashes. Lives in `Modules/Core/app/Rules` (a new directory — no custom Rule
  class existed anywhere in the repo before this) since Core is the one module every other part of
  the application, including root `app/` (per `docs/decisions/0007-in-monolith-blade-admin.md`), is
  allowed to depend on.
- Wired into all five existing locations (`StoreAdminRequest`, `UpdateAdminRequest`,
  `UpdateOwnProfileRequest` for `phone`; `RegisterRequest`, `UpdateProfileRequest` for `mobile`),
  alongside the existing `max:N` — the regex already bounds length tightly, but `max:N` stays as a
  cheap independent safety net, consistent with this codebase's general style of layering simple
  and specific rules rather than relying on one clever regex alone.
- **Normalization to E.164** (`+9665XXXXXXXX` / `+201XXXXXXXXX`) is a separate concern from
  validation, handled by `Modules\Core\Support\PhoneNumber::normalize()` — not a model cast, since a
  cast would silently rewrite the stored value on every read/write.
  - **Amended after initial implementation**: normalizing only in the Service layer (the original
    decision here) meant `unique:admins,phone`/`unique:users,mobile` compared the *raw* submitted
    value against already-normalized stored values — two admins entering the same number in
    different formats (`0512345678` vs `+966512345678`) would both pass uniqueness validation and
    collide. Every phone/mobile-accepting FormRequest now calls `PhoneNumber::normalize()` from
    `prepareForValidation()`, before any rule (including `unique`) runs, so uniqueness checks
    compare like-for-like. The Service-layer calls (`AdminManagementService::{create,update,
    updateOwnProfile}`, `AuthService::register`, `ProfileService::updateProfile`) are kept as a
    harmless, idempotent defense-in-depth in case a Service method is ever invoked from a path that
    isn't one of these FormRequests.
- `admins.phone` has a database-level unique index (matching `email`'s existing one on the same
  table) — validation-level uniqueness alone doesn't survive a race between two concurrent
  requests. Added directly to `create_admins_table`'s column definition (`->nullable()->unique()`)
  rather than a separate follow-on migration, per root `CLAUDE.md`'s dev-phase migration policy —
  this table has no production data to preserve yet, so there's nothing a separate `ALTER TABLE`
  migration protects that editing the source migration and reseeding doesn't already handle.

## Consequences

- `Admin.phone` and `User.mobile` are now stored consistently as `+966XXXXXXXXX` or
  `+20XXXXXXXXXX`, regardless of which local/international format the admin or user actually
  typed — any future code reading these columns can assume E.164, not a mix of formats.
- Expanding to a third market later means adding one more pattern to
  `SaudiOrEgyptianPhoneNumber` (and probably renaming the class) plus one more branch in
  `PhoneNumber::normalize()` — both are single, shared locations, not five separate call sites.
- Existing rows written before this change are not retroactively normalized — this decision only
  affects newly validated/saved values. A backfill would be a separate, explicit change if it's
  ever needed.
- Any future FormRequest accepting a phone/mobile field must normalize in `prepareForValidation()`
  before its `unique`/format rules run, not just call `PhoneNumber::normalize()` later in a
  Service — otherwise the same raw-vs-normalized uniqueness gap reappears.
