# 24. Soft-delete rollout to Admin/Provider (bug fix) and Country/City/Currency/ProviderDocument

## Status

Accepted

## Context

`docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md` established the pattern
(`SoftDeletes` + a partial unique index excluding trashed rows) for `users`. Auditing the rest of
the schema found two different gaps:

- `Admin` and `Provider` already carried `SoftDeletes` at both the migration and model level, but
  their unique columns (`admins.email`/`phone`, `providers.user_id`/`commercial_registration_
  number`) were still *plain* unique indexes — the exact bug ADR 0014 fixed for `users`, left
  unfixed here. A soft-deleted admin/provider permanently blocked re-registration under the same
  email/phone/CR number.
- `Country`, `City`, `Currency`, and `ProviderDocument` had no soft-delete at all, despite being
  either master data referenced by foreign keys elsewhere (a hard delete of a row something else
  references is always the wrong default) or a provider's uploaded verification document (worth
  preserving for the verification audit trail even once superseded).

This was scoped deliberately, not applied to every table in the schema — see root `CLAUDE.md`
Rule 9 (added in this same change) for the resulting standing default and its exclusions.
Explicitly excluded from this pass, each for a specific reason recorded on the model itself:
`UserProfile`, `ProviderVerification`, `SiteSetting` (owned/dependent or singleton records),
`UserMfaSetting`, `MfaRecoveryCode` (already hard-delete-and-replace by design — recoverable
secrets/recovery codes would be a security regression), `VerificationCode`, `PasswordHistory`
(ephemeral OTP and an explicitly append-only audit log, respectively), `NotificationPreference`,
`UserDevice` (always updated in place / no delete action exists anywhere today).

## Decision

- `Admin`: converted `email`/`phone` to partial unique indexes (`admins_email_active_unique`,
  `admins_phone_active_unique`), `WHERE deleted_at IS NULL`.
- `Provider`: converted **both** `user_id` and `commercial_registration_number` to partial unique
  indexes, for consistency — a soft-deleted provider's former owner can activate a new profile, and
  its CR number becomes reusable by any account.
- `Country`, `Currency`: added `SoftDeletes`, converted their `code` column's plain unique index to
  partial (same `WHERE deleted_at IS NULL` shape, no driver branching needed).
- `City`, `ProviderDocument`: added `SoftDeletes` — neither had an existing unique constraint to
  convert.
- Every FormRequest validating uniqueness on one of these now-partial-indexed columns
  (`StoreAdminRequest`, `UpdateAdminRequest`, `UpdateOwnProfileRequest`,
  `Provider/UpdateProviderRequest`, `Provider/ActivateProviderRequest`) got the matching
  `->whereNull('deleted_at')` added to its `Rule::unique(...)` call, so the FormRequest and the DB
  constraint agree — the same companion fix `RegisterRequest` already needed for `users`.
- This is **capability only** — no new "delete a country" or "delete a document" endpoint was
  built. None exist today (no admin master-data screen, no document-removal action), and building
  one would be inventing product functionality speculatively (root `CLAUDE.md` Rule 0). The point
  is that whenever such an action is eventually built, it's a soft-delete for free, and nothing
  needs revisiting at the schema level first.
- All edits were made in place on the original creating migrations (Rule 7 — no production data
  yet), followed by `migrate:fresh --seed`.

## Consequences

- Regression tests exist for each of the four fixed/added unique-column cases (Admin's email+phone,
  Provider's user_id+CR-number, Country's code, Currency's code) proving the specific bug is fixed
  — a bare "delete succeeds" test doesn't catch a missing or wrong index, per ADR 0014's own
  conclusion.
- Root `CLAUDE.md` Rule 9 now makes `SoftDeletes` + a partial unique index the default for any new
  model, not something decided fresh each time — see that rule for the exact exclusion criteria.
