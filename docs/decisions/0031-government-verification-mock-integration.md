# 31. Government verification as a mock integration, admin-applied

## Status

Accepted

## Context

FR-PROVIDER-004 (source spec §3.3, pages 27–36) asks the platform to integrate with the Saudi
government commercial-registration service: a provider's CR number is sent to the government,
official company info comes back, and that imported data may require admin approval before
publication. No real integration exists — no contract, no credentials, no chosen gateway — the
same blocker that deferred this FR entirely in `docs/decisions/0030-provider-taxonomy-portfolio-
and-admin-review.md`'s "Next steps" section. The user asked to build this now anyway, as an
explicit mock: a real check-and-import workflow, behind an interface, with a deterministic fake
client standing in until a real one exists.

## Decisions

- **`Modules\Core\Contracts\GovernmentVerificationClient`** is the one seam — `check(string
  $commercialRegistrationNumber): GovernmentVerificationResult`. `Modules\Core\Services\
  MockGovernmentVerificationClient` is the only implementation today, bound in
  `CoreServiceProvider` the same way `SubscriptionEntitlementChecker` is bound as a placeholder —
  swapping in a real client later needs no change anywhere else.
- **The mock is deterministic, not random**, so tests and manual QA get repeatable results: a CR
  number ending in `"0000"` simulates "service unavailable" (the spec's own edge case), everything
  else returns a successful, obviously-synthetic result (`"Registered Business {crNumber}"` as the
  legal name, etc.).
- **`GovernmentVerificationRequest` is a history log**, not a current-state row like
  `ProviderVerification` — a provider can retry after "unavailable," and every attempt is kept.
  Uses the provider's own already-on-file `commercial_registration_number` (collected at profile
  activation) — the endpoint takes no request body.
- **A completed check never auto-applies to the provider profile.** Per the spec's own "may
  require administrator approval before publication," `Modules\Core\Services\
  GovernmentVerificationService::check()` only ever creates/records the request row; an admin
  applies it separately via `Admin\ProviderController::applyGovernmentVerification()` →
  `ProviderManagementService::applyGovernmentVerification()`, gated by the same `providers.update`
  permission as every other admin-side provider action (no new permission — this is part of the
  same "edit provider" workflow).
- **Applying never overwrites a field the provider already filled in** — the Modules/Core/
  CLAUDE.md gotcha ("government-verification data must never silently overwrite user-entered
  data") is enforced in code, not just documented: only `address`/`year_established` ever merge in
  (both nullable), and only when currently `null`. `company_name` is a required, always-already-set
  field, so it's never a merge target — confirmed directly (Larastan flagged the dead
  `company_name === null` branch when first written, since the column is non-nullable at the
  schema level).
- **`GovernmentVerificationCompleted` is a new, real `NotificationEvent`** (self-triggered, sent
  whether the check succeeded or failed — the provider needs to know either way), matching the
  spec's own "Government verification completed" notification. Not mandatory, EN/AR localized.
- **Not exposed on the public directory** (`GET /providers`, `GET /providers/{id}`) — only on the
  provider's own `GET /providers/me` and the admin show screen. Pre-approval government data is an
  internal/provider-facing concern, not something a project owner should see before an admin has
  reviewed it.

## Consequences

- FR-PROVIDER-004 now has a real, testable workflow end-to-end (`POST /providers/me/government-
  verification` → admin review → `POST .../apply`) instead of being entirely absent — but the
  "real" part is honestly just the workflow shape; the actual government data is fake until a real
  client replaces `MockGovernmentVerificationClient`.
- When a real Saudi CR integration is contracted, only `MockGovernmentVerificationClient` and its
  `CoreServiceProvider` binding change. `GovernmentVerificationRequest`, the service, the admin
  apply action, and the notification all stay as-is.
