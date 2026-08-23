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
