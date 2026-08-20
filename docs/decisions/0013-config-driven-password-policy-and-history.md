# 13. Config-driven password policy and shared password-history

## Status

Accepted

## Context

Password strength rules were previously hardcoded as a literal `Password::min(8)->mixedCase()
->numbers()` (or equivalent) repeated inline in every FormRequest that validates a password. The
spec asks for a "configured password policy" and a password-history requirement (can't reuse a
recent password) for both end users and admins — two requirements that both want a single source
of truth rather than being copy-pasted across `RegisterRequest`, `ChangePasswordRequest`,
`ResetPasswordRequest`, and the admin-side equivalents.

## Decision

- `Modules/Core/config/config.php`'s `password_policy` block (`min_length`, `require_mixed_case`,
  `require_numbers`, `require_symbols`, `uncompromised`, `history_count`) is the single source of
  truth, `env()`-driven. `CoreServiceProvider::boot()` builds one `Password::defaults(fn () => ...)`
  closure from it; every password-validating FormRequest uses `Password::defaults()` instead of a
  literal rule chain. Changing the policy is a config/env change, not a multi-file edit.
- **Password history is a single shared table**, `password_histories`, using a polymorphic
  `morphs('authenticatable')` column pair rather than one table per subject type — `App\Models\User`
  and `Modules\Core\Models\Admin` are both authenticatable subjects that need the exact same
  "was this password used recently" check, and a shared append-only table costs a few extra lines
  of morph wiring versus a second near-identical table and a second near-identical service.
  `PasswordHistoryService::isReused()` checks the subject's *current* password hash plus its last
  `history_count` recorded hashes; `history_count <= 0` disables the check entirely (history is
  still recorded either way, so re-enabling later has real data to check against).
- **Placement of the reuse check differs by flow, and this is deliberate, not inconsistent**:
  `ChangePasswordRequest` (authenticated, current password already proven via
  `current_password:sanctum`) carries `NotAPreviousPassword` directly as a FormRequest rule. The
  unauthenticated OTP-based `resetPassword()` flow does **not** — that check happens inside
  `AuthService::resetPassword()`, only *after* `$verificationCodes->consume()` succeeds. Checking
  reuse before the OTP is consumed would let an attacker with no valid code learn "this account
  exists and this exact password was used before" from a validation error alone — a real
  enumeration side-channel. `NotAPreviousPassword` itself is a no-op when constructed with a null
  subject, which is exactly the unauthenticated-reset case's shape.

## Consequences

- Every one of the six password-write sites (`register`, `resetPassword`, `changePassword` on the
  end-user side; the three admin equivalents) now goes through the same `PasswordHistoryService`
  and the same `Password::defaults()` — there is exactly one place to change the policy or the
  history depth.
- A future admin-facing "password policy" settings screen (if ever built) would only need to write
  to this same config surface — no service/rule code would need to change.
- The reset-password flow's reuse check being Service-level rather than FormRequest-level means a
  developer adding a new password-write path must remember to call `PasswordHistoryService`
  explicitly rather than relying on a rule being present — flagged here since it's the one place
  this pattern isn't uniform across all six sites.
