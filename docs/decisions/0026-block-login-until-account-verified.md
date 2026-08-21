# 26. Login itself is blocked until the account is verified

## Status

Accepted

## Context

`docs/decisions/0015-account-verification-gate-policy.md` gated resource-consuming endpoints
behind `account.verified`, but deliberately left `POST /auth/login` itself reachable for an
unverified account — the account could sign in and reach the identity-repair/credential-hygiene
surface, just not anything trust-bearing. The user asked for a stricter rule: an account that
hasn't verified its email/mobile should not be `active`, and should not be able to log in at all.

Blocking login outright creates a real lockout risk that the previous, looser gate didn't have:
`POST /auth/register` issues a Sanctum token immediately so the new account can complete
verification in the same session (`POST /auth/verify`, `POST /auth/resend-code` — both
`auth:sanctum`-gated). If login is now blocked for an unverified account, anyone who doesn't
verify before that one token is lost (closed tab, expired token, cleared app storage) would have
no path back in at all — not even to resend a code — since the only way to get a fresh token was
`POST /auth/login`, which just started rejecting them.

## Decision

- New `App\Enums\UserStatus::PendingVerification` — the default `status` a fresh registration now
  gets, instead of `Active`. `canAuthenticate()` still returns `true` for it deliberately — that
  method answers "is the account administratively blocked" (suspension), a separate axis from
  "has it completed verification." Folding verification into `canAuthenticate()` would make every
  unverified-account rejection reuse the same generic "contact support" message a suspended
  account gets, losing the ability to tell the two apart.
- `AuthService::login()` gained its own explicit check: `! $user->hasVerifiedIdentity()` (already
  an existing, orthogonal method) throws a **distinct** message — "Please verify your account
  before signing in." — rather than the generic anti-enumeration "credentials do not match"
  message used for a wrong password or unknown account. There's no anti-enumeration value left to
  protect at this point: the account exists and the password is genuinely correct.
- `AuthService::verifyAccount()` promotes `PendingVerification` → `Active` on the first successful
  verification of either channel (email or mobile) — a user re-verifying a *changed* email/mobile
  later (`ProfileService::updateProfile()`'s re-verification trigger) is already `Active` by then,
  so this is a no-op for that case.
- **New public (unauthenticated) recovery pair**, mirroring the existing forgot-password/
  reset-password shape: `POST /auth/resend-verification` and `POST /auth/verify-account`, both
  taking `{login, ...}` instead of relying on an authenticated session. `resend-verification`
  always returns the same generic message regardless of whether the login matches a real,
  not-yet-verified account (anti-enumeration, matching `forgotPassword()`); `verify-account`
  returns the same generic "invalid or expired" message for an unknown login as for a wrong code.
  Neither issues a session on success — once verified, the account signs in normally through
  `POST /auth/login`. A new `verification-public` rate limiter (3/minute, keyed by login+IP,
  matching `login`'s shape) applies to both.
- The existing authenticated `POST /auth/verify`/`POST /auth/resend-code` are unchanged and still
  exist — they're what the registration-issued token actually uses in the common case. The new
  public pair is specifically the fallback for a lost/expired session.

## Consequences

- A returning user who registered but never verified now gets a clear, actionable rejection at
  login instead of silently limited access — closer to the user's actual request than the
  previous "logged in but blocked from everything" shape.
- The lockout risk this change would otherwise introduce is closed by the public recovery pair —
  verification is never unreachable, regardless of session state. Removing that pair without an
  equivalent replacement would reopen a real lockout; `Modules/Core/routes/api.php`'s own
  file-level comment flags this explicitly for anyone touching that route group later.
- `App\Enums\UserStatus` now has three cases instead of two — anywhere reasoning about "is this
  account okay" needs to consider `PendingVerification` distinctly from `Active`, not just
  `Active` vs. `Suspended`. Confirmed via grep that `CapabilityService`'s `isProjectOwner`
  computation already ANDs `canAuthenticate()` with `hasVerifiedIdentity()` separately, so it
  needed no change — a `PendingVerification` account was already correctly excluded there before
  this enum case existed.
