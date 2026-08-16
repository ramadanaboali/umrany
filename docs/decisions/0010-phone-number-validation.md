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
  validation, handled by `Modules\Core\Support\PhoneNumber::normalize()`, called explicitly at the
  point of persistence in the Service layer (`AdminManagementService::{create,update,
  updateOwnProfile}`, `AuthService::register`, `ProfileService::updateProfile`) — not a model cast.
  A cast would silently rewrite the stored value on every read/write; an explicit call at the one
  place each field is actually set is easier to reason about and matches how this codebase already
  treats other Service-layer-only concerns (see `docs/architecture/backend-layering.md`).

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
