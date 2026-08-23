# 27. Admin can suspend/reactivate a user, with a required reason

## Status

Accepted

## Context

`Modules\Core\Notifications\AccountSuspendedNotification`/`AccountActivatedNotification` have
existed since the notification system was first built, both explicitly documented as having "no
dispatch site yet" — no admin action changed a `User::$status` after registration. The user asked
for exactly that: an admin can suspend a user (with a reason), a suspended user cannot log in, and
the admin can reactivate them.

## Decision

- `users` table gains `suspension_reason` (nullable text), `suspended_at` (nullable timestamp),
  `suspended_by_admin_id` (nullable FK to `admins`). Overwritten on each new suspension — not a
  history table (root `CLAUDE.md` Rule 0, not asked for); reactivating nulls all three out again.
- `Modules\Core\Services\Admin\UserManagementService::suspend(User, Admin $actor, string $reason)`:
  revokes every session token immediately (matching the existing pattern for other
  security-relevant transitions — password reset/change already do this), sets status to
  `Suspended` + records the reason/timestamp/actor, dispatches `AccountSuspendedNotification`
  (updated to accept and include the reason) — its first real dispatch site.
  `reactivate(User)`: status back to `Active`, clears the three fields, dispatches
  `AccountActivatedNotification` — likewise its first dispatch site.
- Both actions reuse the existing `users.update` permission (no catalog change) — consistent with
  that permission's existing scope as "the mutating admin action on users."
- `App\Enums\UserStatus::canAuthenticate()` already returns `false` for `Suspended` — no change
  needed there; a suspended user is rejected at the existing `AuthService::login()` gate the same
  way it always has been for that status.

## Consequences

- A suspended user's already-issued tokens stop working immediately, not just future login
  attempts — an admin suspending an account for cause (e.g. abuse in progress) actually stops it
  right away.
- No suspension history is kept — only the *current* suspension's reason/date/actor are visible.
  If an audit trail across multiple suspend/reactivate cycles is ever needed, that's a genuinely
  new requirement (a history table), not a gap in this change.

## Extension: admin profile editing also reuses `users.update`

Per the "Administrators may update user profiles according to assigned permissions" requirement:
`PUT /admin/users/{user}` (`UserController::updateProfile()`) lets an admin edit a user's
name/email/mobile/address/country/city — scoped with the user directly to reuse the existing
`users.update` permission rather than add a new one, consistent with this ADR's original framing
of that permission as "the mutating admin action on users," not a suspend/reactivate-only grant.
Avatar/language/currency stay self-service-only — those are personal preferences, not
administrative data an admin has a reason to override.

The FormRequest (`App\Http\Requests\Admin\UpdateUserProfileRequest`) mirrors
`Modules\Core\Http\Requests\Profile\UpdateProfileRequest`'s validation (uniqueness, city-belongs-
to-country, at-least-one-of-email/mobile) but resolves the target user from the route binding
(`$this->route('user')`), never `$this->user()` — the latter is the acting *admin* under the
`admin` guard, not the user being edited. The controller then calls
`Modules\Core\Services\ProfileService::updateProfile()` directly — the exact same service method
the end-user's own `PUT /api/v1/core/profile` uses — so an admin-triggered email/mobile change
clears verification and re-issues a code identically to a self-service one, with no duplicated
business logic between the two call sites.
