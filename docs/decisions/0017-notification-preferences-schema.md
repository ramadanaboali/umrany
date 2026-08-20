# 17. Notification preferences: one row per (user, event), a boolean column per channel

## Status

Accepted

## Context

The spec asks for 8 notification events, each independently configurable per channel
(in-app/push/email), with push explicitly out of scope for this pass (only in-app + email are
built). There are two natural schema shapes for "per-user, per-event, per-channel opt-in": a wide
table (one row per `(user_id, event_type)`, one boolean column per channel) or a tall table (one
row per `(user_id, event_type, channel)`, a single boolean value column).

The tall shape is the more conventional choice for genuinely open-ended channel sets, since adding
a channel needs no schema change. But this platform has exactly 2 channels today and 3 once push
ships — nowhere near the point where that flexibility pays for itself, and it roughly doubles row
count and query complexity ("fetch the whole matrix for a user" becomes a group-by instead of a
plain select) for no near-term benefit.

## Decision

- `notification_preferences`: one row per `(user_id, event_type)`, with `in_app`/`email` boolean
  columns directly on the row (a third `push` column is the natural addition whenever push ships —
  not built now, per root `CLAUDE.md` Rule 0).
- A user with **zero stored rows for an event still gets a complete, correct matrix** —
  `NotificationPreferenceService::matrixFor()` merges stored rows over
  `NotificationEvent::defaultChannels()` (both channels on by default — a user opts *out*, rather
  than having to opt *in* to notifications about their own account security). No backfill
  migration needed at rollout; no row is written until a user actually changes something.
- `NotificationEvent::isMandatory()` marks 5 of the 8 events (verification code, password
  changed/reset, suspicious login, account suspended) as non-optional — the preferences endpoint
  accepts but silently ignores an attempt to disable one of these, rather than rejecting the whole
  request over one ignored field.
- `Modules\Core\Notifications\BaseUserNotification` is the shared abstract base every concrete
  notification extends — its `via()` is the single place `NotificationPreferenceService::allows()`
  gets asked, so no concrete notification class re-implements channel-selection logic. It never
  requests the `mail` channel for an account with no email on file, independent of the stored
  preference — in-app notifications have no such dependency.
- Reuses the framework's own `DatabaseNotification`/`Notifiable::notifications()` for the in-app
  channel — no bespoke Notification Eloquent model — matching this codebase's existing precedent of
  reusing Sanctum's own token table instead of a custom sessions table.

## Consequences

- Adding push later is a 3-column/1-enum-case change (`push` boolean column,
  `NotificationChannel::Push` case, one more branch in `BaseUserNotification::via()`) — no schema
  migration touching existing rows' meaning, since the wide shape doesn't need reshaping to add a
  channel, only extending.
- If the channel count ever grows well past today's 2-3, this schema's row-width becomes the
  awkward part (a new column per channel forever) and revisiting the tall-table shape would be the
  right call then — not a decision this ADR forecloses, just not the right tradeoff at 2 channels.
