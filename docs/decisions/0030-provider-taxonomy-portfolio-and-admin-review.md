# 30. Provider business categories, portfolio/certificates, public directory, and admin review

## Status

Accepted

## Context

Provider identity/verification (activate, company info, upload documents) already existed, but
nothing built on top of it: there was no business-category taxonomy (FR-PROVIDER-003), no
portfolio/certificate showcase (FR-PROVIDER-006/007), no public directory a project owner could
actually browse, and — the critical gap — no admin action could ever approve, reject, publish, or
suspend a provider. `ProviderVerificationStatus`/`ProviderStatus` already modeled every state this
needed; nothing consumed them. The user asked for this whole slice at once: categories, portfolio,
certificates, the public directory, the admin review screen, 5 new notification events, and a
partial/honest public statistics block — fully localized (EN/AR) throughout.

## Decisions

### Subcategory deferral

`ProviderSubcategory` is explicitly **not** built in this phase — `ProviderCategory` only. The
source specification pairs a subcategory with each category, but no taxonomy content for the
30-100 likely subcategories exists to seed, and nothing downstream (portfolio, public directory
filters) needs it yet. `Modules/Core/CLAUDE.md`'s entity list and `docs/modules/core.md`'s Provider
section both call this out explicitly as deferred, not missing.

### Portfolio: images are uploads, videos are URLs

`ProviderPortfolio.images` is a JSON array of `public`-disk paths — uploaded files, stored under
`providers/{id}/portfolio/{portfolioId}`, same convention as `Provider.logo_path`/`cover_path`.
`ProviderPortfolio.videos` is a JSON array of plain URLs, never uploads — a provider is expected to
link an externally-hosted video (YouTube, Vimeo, etc.), not upload raw video files this platform
would need to store/transcode/serve. This keeps the storage model asymmetric on purpose: images go
through the same upload pipeline as logo/cover; videos are just validated URLs.

### `providers.update` covers the whole review + lifecycle workflow

New permissions: `providers.list`, `providers.view`, `providers.update` — 3 actions, not the usual
5 (`docs/decisions/0009-granular-crud-admin-permissions.md`'s convention), the same asymmetry as
`users.*`. Providers are self-created by the account holder (`POST /api/v1/core/providers`), never
by an admin, so there is no "create a provider" admin screen to gate; providers are never
admin-deleted either (suspension is the closest equivalent, and that's an update). `providers.update`
deliberately covers approve, reject, suspend, reactivate, and company-info edit — one permission
for the whole admin-side provider workflow, exactly like `users.update` already covers
suspend+reactivate+profile-edit. Splitting `approve`/`reject`/`suspend` into separate catalog action
names was considered and rejected: it would require touching `PermissionController::ACTION_ORDER`
and the shared `admin.permissions.actions.*` lang keys for a single resource's bespoke action set,
disproportionate to the actual authorization need (any admin trusted to move a provider through
this workflow at all is trusted for every step of it).

### Approve also publishes — one admin action, not two

Per the spec's workflow, `ProviderManagementService::approve()` does two things in one transaction:
sets the verification to `Approved` (with `reviewed_by`/`reviewed_at`) **and** sets the provider's
own `status` to `Published`. This was a deliberate choice, not an oversight — `Provider` and
`ProviderVerification` are two separate rows specifically so a suspended-but-still-approved state
can exist later (`suspend()` only ever touches `Provider.status`, never verification), but there is
no product reason to make an admin click "approve" and then separately click "publish" for the
first-time-approval path. `reject()`, by contrast, never touches `Provider.status` — a rejected
verification doesn't retroactively unpublish anything, since a provider can only be rejected before
ever being published in the first place (there's no path from `Published` back through `Pending`).

### A fifth notification event: verification submitted

The source spec's "Notifications generated" list for Provider names
"Verification submitted/approved/rejected... profile published/suspended" — five events, not four.
`ProviderVerificationSubmitted` is self-triggered from `ProviderService::submitVerification()`
(explicit `$locale = app()->getLocale()`, same rule as `RegistrationCompleted`); the other four
(`ProviderVerificationApproved`, `ProviderVerificationRejected`, `ProviderProfilePublished`,
`ProviderProfileSuspended`) are admin-initiated from `ProviderManagementService` (no explicit
`$locale` — the recipient's own stored preference wins, same rule as `AccountSuspended`/
`AccountActivated`). All five are opt-out-able (`isMandatory() => false`), same tier as
`RegistrationCompleted`/`AccountActivated` — none of them are security-shaped events a user must be
forced to receive.

### Public statistics stay partial and honest

`ProviderResource`'s `statistics` sub-array exposes `years_of_experience` as a real computation
(`now()->year - year_established`) but `completed_projects`, `active_products`, `average_rating`,
`review_count`, `response_rate`, `response_time` as `0`/`null`, each with a one-line comment naming
the future module it depends on (Projects, E-Commerce, the Reviews sub-area, Core's own Chat
sub-area, respectively). Fabricating plausible-looking numbers for fields with no real data source
yet was rejected — an honest `0`/`null` with a comment is strictly better than a number a client
might render as if it meant something.

## Consequences

- A project owner can now browse a real public provider directory (`GET /api/v1/core/providers`,
  filterable by city/country/category/company-name search) — this was previously impossible; every
  provider that had ever activated was invisible to everyone but its own owner.
- An admin can now actually move a provider through its entire lifecycle end-to-end. Before this
  change, `ProviderStatus::Published` and `ProviderVerificationStatus::Approved` were reachable
  only by direct database/seeder manipulation (as `DemoUserSeeder` already did) — no code path
  could ever produce them from a real review action.
- Adding `ProviderSubcategory` later is additive (a new table + pivot + seed content), not a
  redesign — `ProviderPortfolio.provider_category_id` and the public directory's category filter
  both already reference `ProviderCategory` by id, so introducing a subcategory layer underneath it
  doesn't require touching either.

## Update: post-implementation review fixes and edge-case coverage

A post-implementation code review found and fixed several real gaps before this was considered
done:

- `filter[city_id]`/`filter[country_id]` on the public directory were silently doing a
  substring (`LIKE '%value%'`) match instead of an exact match — bare strings passed to Spatie's
  `allowedFilters()` default to `AllowedFilter::partial()`. Fixed to `AllowedFilter::exact()`, with
  a regression test proving a filter value that's a literal substring of another provider's id no
  longer wrongly matches it.
- Portfolio/certificate "update" routes accepting file uploads were registered as `PUT`, which
  PHP never populates `$_FILES` for — a genuine multipart update would silently drop the file with
  no error. Changed to `POST` (matching every other upload endpoint in this module — logo, cover,
  avatar all use `POST`).
- Neither `ProviderResource` nor its `portfolios`/`certificates` relations were ever eager-loaded
  on the show endpoints — a project owner viewing a provider's public profile (the whole stated
  purpose of the public directory) never saw any portfolio/certificate data. Fixed by adding both
  to `ProviderResource` and eager-loading them in `show()`/`showPublic()`/`paginatePublic()`.
- `updatePortfolio()`/`updateCertificate()` deleted the old media file from disk *before* the
  DB update was committed — a failed update left the row still pointing at a file that had already
  been permanently deleted. Reordered so the new file is stored and the DB row committed first;
  the old file is deleted only after that succeeds.
- `DemoUserSeeder` exercised the new categories pivot but not `ProviderPortfolio`/
  `ProviderCertificate` (root CLAUDE.md Rule 6 names "a new Provider sub-entity" as its explicit
  example) — extended to seed one of each for the demo provider.
- The admin `show.blade.php` re-implemented "is this verification reviewable" as a hardcoded
  string comparison instead of calling `ProviderVerificationStatus::isReviewable()`, risking drift
  from the backend's own definition. Fixed to call the enum method.

Three of the source spec's named edge cases lacked direct test coverage and were added: two
simultaneously *active* providers cannot share a commercial registration number (distinct from the
already-tested soft-delete-reuse case); a rejected verification can be resubmitted and then
approved end-to-end; changing categories on an already-Published, Approved provider does not
unpublish it (confirming the "no re-review required" behavior is deliberate, not an accidental
gap).

## Next steps (explicitly deferred, not started)

Two FRs from the source spec's Provider Profile section remain **entirely unimplemented** — no
model, no stub, no placeholder code. Both were deliberately left this way rather than built
speculatively (root `CLAUDE.md` Rule 0), because each is genuinely blocked on something outside
this change's scope:

- **Government Verification (FR-PROVIDER-004).** Blocked on an external dependency: a real
  contract/credentials for the Saudi commercial-registration service. There is nothing to
  implement against until that integration detail (endpoint, auth method, response schema) is
  known — building a stub now would just be guessed shape that gets thrown away. When it's ready
  to build: a `GovernmentVerificationRequest`/`GovernmentBusinessData` model pair (per
  `docs/modules/core.md`'s Verification section), imported data stored with a source reference and
  never silently overwriting user-entered fields (per `Modules/Core/CLAUDE.md`'s Gotchas), manual
  verification staying available as a fallback if the integration call fails.
- **Subscription-Gated Features (FR-PROVIDER-009)** — verified badge, featured listing, max
  project offers, AI features, premium visibility. Blocked on the entire Subscription sub-area
  (`SubscriptionPlan`/`SubscriptionFeature`/`PlanFeature`/`ProviderSubscription`) not existing yet —
  that's `docs/business/roadmap.md` Phase 2 in full, a separate large body of work, not a
  Provider-scoped addition. `Modules\Core\Services\SubscriptionEntitlementChecker::hasAccess()`
  already exists as the intended wiring point (currently hardcoded `true`) — when Phase 2 lands,
  that's where real plan/feature checks plug in; `Provider`'s `has_ecommerce_access`/
  `has_erp_access` columns are the same kind of pre-placed, currently-inert hook.

Both are tracked in `docs/business/roadmap.md` (Phase 1's "Not yet built" line for Government
Verification; Phase 2 in full for Subscription) — this section exists so a future reader lands on
one explicit, discoverable answer for "why doesn't this exist yet" without having to reconstruct
it from roadmap prose.
