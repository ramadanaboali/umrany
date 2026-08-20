# 12. TOTP MFA via a two-step login exchange

## Status

Accepted

## Context

The spec calls for authenticator-app MFA, opt-in (default disabled), enrollable either at
registration or later via the profile. This is a brand-new subsystem — nothing to extend, no
existing MFA/TOTP code or package in the repo.

Two real design questions had to be settled: (1) which TOTP library, and (2) how a login that
requires a second factor actually flows through an API that otherwise issues a Sanctum token
immediately on successful password verification.

## Decision

- **Library**: `pragmarx/google2fa` for TOTP generation/verification, `bacon/bacon-qr-code` to
  render the enrollment QR as inline SVG (no external QR-image service, no extra HTTP round trip
  for the client). `Google2FA::verifyKeyNewer($secret, $key, $lastUsedTimestamp, $window)` is used
  instead of plain `verifyKey()` specifically because it returns the matched time-slice counter
  (an `int`, possibly `0` — never treat it as falsy) and rejects a slice at or before
  `$lastUsedTimestamp`, closing the replay window where the same 6 digits could otherwise be reused
  for the rest of their 30-second validity.
- **Storage**: dedicated `user_mfa_settings` (secret, `confirmed_at`, `last_used_timestamp`) and
  `mfa_recovery_codes` (hashed, single-use) tables — not columns on `users` — matching this
  codebase's existing precedent of `UserProfile` living separately from `User`.
- **Enrollment never enforces itself until confirmed.** Whether started at registration
  (`mfa_enroll: true`) or later via `POST /auth/mfa`, the created `UserMfaSetting` row has
  `confirmed_at: null` and login-time enforcement (`User::hasMfaEnabled()`) requires it to be
  non-null. An abandoned enrollment — the user never scans the QR, or scans it but never submits a
  valid code — can never lock the account out.
- **Two-step login exchange**: when `AuthService::login()` finds `hasMfaEnabled()` true, it does
  *not* issue a Sanctum token. It generates an opaque random challenge token, caches the pending
  login's context (`user_id`, `device_name`, `ip`, `attempts`) under
  `hash('sha256', $token)` — not the token itself, so a cache dump can never hand out live
  challenges — and returns `{mfa_required: true, challenge_token, expires_in}`. The client then
  calls `POST /auth/mfa/challenge` with `{challenge_token, code}`; on success this exchanges for
  exactly the same session-issuance path (`AuthService::issueAuthenticatedSession()`) a direct
  login would have used, returning the identical response envelope. One generic error
  ("This challenge is invalid or has expired") covers both an unknown/expired token and a wrong
  code, preserving the login endpoint's existing no-enumeration posture. A configurable attempt cap
  (`core.mfa.challenge_max_attempts`, default 5) invalidates the challenge outright once exceeded.
- Disabling MFA or regenerating recovery codes both require the current password **and** a valid
  TOTP/recovery code — a stolen session token alone must never be sufficient to turn MFA off.

## Consequences

- `issueAuthenticatedSession()` is now the single place a Sanctum token is minted for a login,
  shared by the direct-login-success path and the post-challenge-success path — device recognition
  and login enrichment (ADR 0016, ADR 0018) both hook in exactly once here rather than being
  duplicated across every path that can end in an issued session.
- A confirmed MFA setup effectively adds one more round trip to every login for that account — an
  accepted, expected cost of two-factor auth, not a bug.
- Recovery codes are the only self-service MFA recovery path if the authenticator device is lost;
  there is no admin-assisted MFA reset built in this pass (would be a real feature, not built
  speculatively per root `CLAUDE.md` Rule 0).
