# 15. Account verification gate: block resource-consuming actions, never identity-repair or exit

## Status

Accepted

## Context

`Modules\Core\Http\Middleware\EnsureAccountIsVerified` (`account.verified`) already existed and
worked, but was only attached to the Provider route group. The spec says "user cannot do anything
(even logged in) before verification" — read literally, that would gate every authenticated
endpoint, including the ones a user needs specifically *because* they aren't yet verified or
because their account may be compromised.

Two concrete failure modes rule out a literal "gate everything" reading:

- If `PUT /profile` (the endpoint that lets a user *correct* a mistyped email/mobile) were gated,
  a user who registered with a typo could never fix it, since fixing it is itself the thing that
  would need to be unblocked first — an unrecoverable lockout.
- If password/MFA/session management were gated, an unverified-but-legitimate owner of a
  possibly-compromised account could not rotate a leaked password or kill a rogue session until
  *after* proving their identity via a channel that session might not even control anymore — gating
  credential hygiene behind verification actively worsens security instead of improving it.

## Decision

**Governing principle** (stated as a comment at the top of `Modules/Core/routes/api.php`): gate
everything that consumes resources or produces platform-visible state; never gate what an account
needs to repair its identity, secure its credentials, or leave.

- **Stays ungated**: `PUT /profile` (identity repair — already re-triggers verification on any
  email/mobile change), `PUT /auth/password`, all MFA management (`/auth/mfa*`), all session
  management (`/auth/sessions*`, `/auth/logout*`), `DELETE /auth/account`, `POST /auth/verify`,
  `POST /auth/resend-code`.
- **Becomes gated** (`account.verified` + `auth:sanctum`): `GET /me/capabilities`,
  `POST|PUT|DELETE /profile/avatar`, `PUT /profile/language`, `PUT /profile/currency`, every
  `/providers/*` route, the notification-preference and notification-list endpoints.
- Middleware attachment is the only change — `EnsureAccountIsVerified` itself needed no code
  changes, only which route groups reference it.

## Consequences

- A handful of authenticated endpoints deliberately never 403 on verification status, which reads
  as an inconsistency if this ADR isn't the first thing a future maintainer checks — the route
  file's own governing-principle comment and this ADR are the two places that inconsistency is
  explained, not left implicit.
- Any new endpoint added to Core's API needs an explicit decision about which side of this line it
  falls on, using the same test: does it consume resources / produce visible state (gate it), or is
  it identity-repair / credential-hygiene / an exit action (don't).
- `VerificationGateTest` enumerates the full gated/ungated route list as a single data-provider-style
  test — the one place a future route addition that lands on the wrong side of the line would get
  caught, provided that test is kept in sync with the route file.
