# 16. Registration captures account-type intent only, never an authorization source

## Status

Accepted

## Context

The spec asks that end users be able to register (and be recognized at other identity endpoints)
as multiple simultaneous "hats" — project owner, provider, etc. — matching this codebase's existing
architectural rule (`docs/architecture/module-boundaries.md` § User capability resolution) that
capability is always computed dynamically from Provider/subscription/verification state, never a
stored role. That existing rule already answers "how is capability decided," but says nothing about
whether registration should capture what the user *said* they wanted, which the spec's UX clearly
expects (an "I am a:" selector at signup).

## Decision

- New backed string enum `Modules\Core\Enums\AccountType` (`ProjectOwner = 'project_owner'`,
  `Provider = 'provider'`), mirroring the existing `SocialPlatform` enum pattern.
- New nullable `account_types` JSON column on `users` (added to the same migration edited for ADR
  0014, per root `CLAUDE.md` Rule 7). `RegisterRequest` accepts an optional `account_types: array`
  of enum values, defaulting to `['project_owner']` in `prepareForValidation()` when omitted — every
  active, verified account already implicitly *can* act as a project owner, so that's the correct
  default, not an arbitrary one.
- **This is captured intent only — it does not create or activate anything.** Selecting
  `provider` at registration does not create a `Provider` row; `POST /providers` remains the one
  real Provider-activation step, unconditionally, regardless of what was selected at registration.
  `account_types` is never read by any authorization check.
- `UserCapabilities` gained two read-only fields: `isProjectOwner` (derived: active status +
  `hasVerifiedIdentity()`, not the stored `account_types` value — there is no real Projects-module
  concept yet to key a stored flag off) and `accountTypes` (echoes the stored intent verbatim, for
  the client's own "which hats did I say I wanted" UI). The Redis capability cache key was bumped to
  `:v2:` because this changed the cached DTO's constructor shape — see the class-level docblock on
  `CapabilityService` for the specific `__PHP_Incomplete_Class` failure mode this avoids.

## Consequences

- A client cannot infer "is this a real, active provider" from `account_types` — it must check
  `capabilities.is_provider` (computed from the real `Provider` row), same as today. `account_types`
  answers "what did the user say at signup," a UI/intent question, not an authorization one.
- If a future Projects module wants "is this account genuinely acting as a project owner" to become
  a real, richer computed signal (e.g. "has an active project"), that's a `CapabilityService` change
  only — `account_types`'s meaning and shape don't need to change.
- Every future identity endpoint that wants to surface or update this intent (the spec's "other
  identity endpoints") should read/write the same `account_types` column through the same enum,
  not invent a parallel signal.
