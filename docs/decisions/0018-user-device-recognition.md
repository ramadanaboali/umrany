# 18. Device recognition via a dedicated table, not inferred from Sanctum tokens

## Status

Accepted

## Context

The spec requires a "login from a new device" notification. The obvious shortcut — infer "new
device" from whether a matching Sanctum personal-access-token row already exists — doesn't actually
work: tokens are deleted on logout, on `changePassword()`, and on `resetPassword()` (all three
revoke sessions deliberately), and Sanctum's token table carries no IP/user-agent history anyway.
Inferring device novelty from token existence would false-positive *every* post-logout login as
"new device," which is both wrong and exactly the kind of notification-fatigue outcome that
defeats the point of a "new device" alert.

## Decision

- New `user_devices` table, independent of the token lifecycle: `user_id`, `fingerprint`,
  `device_name`, `ip_address`, `user_agent`, `first_seen_at`, `last_seen_at`.
  `fingerprint = sha256(user_agent . '|' . device_name)` — good enough to recognize a returning
  device without storing anything more identifying than what the request already sends, and
  deliberately not a persistent client-side device ID (none exists on this API today).
- `DeviceRecognitionService::recognize()` is called exactly once, from
  `AuthService::issueAuthenticatedSession()` (see ADR 0012 — the single session-issuance point
  shared by direct login and MFA-challenge success). It returns `true` only the first time a given
  fingerprint is seen for that user, at which point `NewDeviceLoginNotification` fires; on every
  subsequent sighting it just touches `last_seen_at`/`ip_address` and returns `false`.

## Consequences

- Device recognition survives logout/password-reset/session-revocation, unlike a token-based
  inference would — a user logging back in from their everyday laptop after a normal logout does
  not get treated as a new device.
- The fingerprint is coarse (no client-generated device ID, no cookie) — two different physical
  devices that happen to send an identical user-agent string and the same client-supplied
  `device_name` would be treated as the same device. Accepted as good-enough for a first pass;
  a real device-ID scheme (e.g. a client-generated UUID persisted on-device) would be the natural
  upgrade if this granularity ever proves insufficient in practice.
- `user_devices` is a natural future foundation for a "your devices" self-service screen (see
  session management), though building that screen itself is out of scope for this pass (root
  `CLAUDE.md` Rule 0) — only the recognition table and hook exist today.
