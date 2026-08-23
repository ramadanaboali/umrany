# 28. Login requires the specific identifier used to itself be verified

## Status

Accepted

## Context

`docs/decisions/0026-block-login-until-account-verified.md` blocks login for an account with no
verified channel at all (`User::hasVerifiedIdentity()` — true if *either* email or mobile is
verified). That left a gap: a user who verified their email but never their mobile could still log
in by typing their (unverified) mobile number as the `login` value — the check only asked "has the
account verified something," never "is the specific identifier just typed itself verified."
Mobile numbers get reassigned/recycled by carriers in a way email addresses generally don't, so
letting an unverified mobile work as a login identifier — even on an otherwise-verified account —
is a real gap, not just a cosmetic one.

## Decision

- `AuthService::login()` gained a second, more specific check *after* the existing
  `hasVerifiedIdentity()` gate (which still runs first and still produces its own message for a
  fully-unverified account): resolve whether `$login` matched the account's `email` or `mobile`
  (same case-insensitive comparison `AuthController::channelFor()` already uses for OTP routing),
  then require that specific column's `*_verified_at` to be non-null. Symmetric — email and mobile
  are each held to the same standard, not just mobile.
- **Distinct message per case**, deliberately not reusing the "please verify your account" message
  from ADR 0026: "This email address is not verified yet..." / "This mobile number is not verified
  yet...". The two failure modes are genuinely different for the user to act on ("you've never
  verified anything" vs. "you verified the other channel, try that one or verify this one too").
- Password-reset/forgot-password are explicitly **not** affected — confirmed with the user
  directly. The OTP mechanism there re-proves control of whichever channel it's sent to at the
  moment the code is consumed, regardless of that channel's prior verification state; requiring
  pre-existing verification there as well would risk permanently locking someone out of resetting
  a password via a channel they registered with but never verified.

## Consequences

- A user with one verified and one unverified channel must either use the verified one to log in,
  or verify the other one first (via the existing verify/resend endpoints) — they can no longer
  authenticate with an unverified identifier just because the account has verified something else.
- This only changes the *login* path. `hasVerifiedIdentity()` itself (used elsewhere — e.g.
  `CapabilityService`'s `isProjectOwner` computation) is unchanged and still means "verified
  something," which remains the correct question for that use.
