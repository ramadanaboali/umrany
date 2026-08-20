# 14. User soft-deletes via partial unique indexes, not a status enum case

## Status

Accepted

## Context

`Modules\Core\Models\Admin` already used Eloquent `SoftDeletes`. `App\Models\User` instead had an
unreferenced `UserStatus::Deleted` enum case — two competing "this account is gone" signals that
didn't actually agree with each other, and neither one freed a deleted account's email/mobile for
re-registration. The spec asks for self-service account deletion and requires that a deleted
account's identifiers become available again.

The obvious approach — add `SoftDeletes` to `User` — runs into a real problem: the existing
`users_email_unique`/`users_mobile_unique` database constraints don't know about `deleted_at` and
would keep rejecting a new registration that reuses a soft-deleted account's email, even though
`Rule::unique(...)->whereNull('deleted_at')` at the FormRequest layer would happily allow it — the
FormRequest and the database would disagree, and the database wins.

## Decision

- Add `$table->softDeletes()` to `User`'s table (edited in place per root `CLAUDE.md` Rule 7 — this
  project has no production data yet). Add `SoftDeletes` to `App\Models\User`.
- **Remove `UserStatus::Deleted`** (confirmed via grep: referenced nowhere outside its own
  declaration) — `deleted_at` is now the only "this account is gone" signal.
- **Replace the plain unique indexes with partial unique indexes that exclude trashed rows**:
  ```sql
  CREATE UNIQUE INDEX users_email_active_unique  ON users (email)  WHERE deleted_at IS NULL;
  CREATE UNIQUE INDEX users_mobile_active_unique ON users (mobile) WHERE deleted_at IS NULL;
  ```
  This syntax is identical on Postgres and SQLite (≥3.8, which is what the test suite runs) for a
  plain `IS NULL` partial condition — no driver branching needed here, unlike a boolean-literal
  partial index (see ADR 0020) which does need one. `RegisterRequest`'s own `Rule::unique(...)`
  gained `->whereNull('deleted_at')` to match, so the FormRequest and the database constraint now
  agree.
- Self-service deletion (`DELETE /auth/account`, password-confirmed via `current_password:sanctum`)
  revokes every session token first, then soft-deletes. Deliberately **not** admin-initiated in this
  pass — end users delete their own accounts; there is no admin "delete a user" action (see ADR
  0009's five-action CRUD pattern not being applied to `users.*` — only `list`/`view`/`update`
  exist, on purpose).

## Consequences

- A soft-deleted user's email/mobile can be reused by a brand-new registration immediately — this
  is the intended behavior, not a bug, and the one thing a partial-index test must actually verify
  (a plain "delete succeeds" test wouldn't catch a missing/wrong index).
- Any new query against `User` automatically respects the SoftDeletes global scope; the one place
  that deliberately needs to see trashed rows for validation purposes (e.g. confirming an email is
  genuinely free) already routes through the FormRequest's own `whereNull('deleted_at')`-qualified
  rule rather than an accidental `withTrashed()` call.
- `EloquentUserRepository::paginateForAdmin()` (the admin Users listing, ADR-adjacent to session
  management) also naturally excludes soft-deleted users with no extra code, since it queries
  through the model.
