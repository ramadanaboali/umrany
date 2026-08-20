Shared-platform-services module: identity, provider profiles, subscriptions, verification, chat,
notifications, finance/wallets, reports, and admin/RBAC for the whole UMRANY platform.

See `docs/modules/core.md` for the full reference (business objectives, FR groupings, source-doc
API prefixes per sub-area).

**Implementation status**: Auth (including TOTP MFA, password history, soft-delete/account
deletion, session management), Profile, Provider identity/verification (manual documents only —
no government-CR integration yet), in-app + email Notifications (push deferred), Admin/RBAC, and
`SiteSetting` (branding/contact/social config) are built and tested (see "What's actually
implemented" below). Subscription, Chat, Finance/Wallet, Reports, CMS/SEO, `SystemSetting`,
`AuditLog`, and the internal sales CRM are still just the target-domain description below, not
implemented — this is a phased build per `docs/business/roadmap.md`, not a gap to fill
speculatively.

## Entities

- User — core account (mobile/email unique, password, terms acceptance, verification/account
  status, soft-deletable, `account_types` — captured registration intent, see
  `docs/decisions/0016-account-type-intent-capture.md`)
- UserSession — **not a dedicated table**: a Sanctum `personal_access_tokens` row already is the
  per-device session record (`Http/Controllers/SessionController`), configurable expiry via
  `config('sanctum.expiration')` (`SANCTUM_EXPIRATION_MINUTES`), pruned daily by
  `sanctum:prune-expired` (see `docs/architecture/infrastructure.md` § Scheduled tasks). User- and
  admin-terminable (the admin side via the Users screen — see `docs/architecture/admin-portal.md`).
- VerificationCode — single-use, time-limited code for account verification / password reset
- UserMfaSetting / MfaRecoveryCode — TOTP MFA secret + confirmation state, and single-use hashed
  recovery codes. See `docs/decisions/0012-totp-mfa-with-two-step-login.md`.
- PasswordHistory — append-only, shared (polymorphic) by both `User` and `Admin` — see
  `docs/decisions/0013-config-driven-password-policy-and-history.md`.
- PasswordReset — **not a dedicated table**: the same `VerificationCode` mechanism (purpose
  `password_reset`) handles this for end users; admins use Laravel's native password-broker table
  (`admin_password_reset_tokens`) instead — see `docs/architecture/admin-portal.md` § Authentication.
- UserDevice — device tied to a user, used for new-device-login detection
  (`Services/DeviceRecognitionService`) — see `docs/decisions/0018-user-device-recognition.md`.
- UserProfile — one-to-one profile: name, avatar, mobile, email, country, city, address, language, currency
- NotificationPreference — per-user/per-event-type opt-in, one boolean column per channel (in-app,
  email built; push deferred) — see `docs/decisions/0017-notification-preferences-schema.md`.
  In-app notifications themselves are **not a custom model** — the framework's own
  `DatabaseNotification`/`Notifiable::notifications()` is reused directly, matching this module's
  existing "reuse Sanctum's own token table" precedent.
- Country / City / Currency — shared master data for addresses and localization
- Provider — service-provider business profile (one per user account); company info, categories, verification, subscription-gated features
- ProviderCategory / ProviderSubcategory — business category taxonomy for providers
- ProviderDocument — uploaded docs for manual verification review
- ProviderPortfolio — past-project showcase items
- ProviderCertificate — certificates/awards/memberships
- ProviderStatistics — public stats (rating, completed projects, response rate/time, etc.)
- SubscriptionPlan — admin-defined plan: price, billing period, country, currency, display order
- SubscriptionFeature / PlanFeature — dynamic feature grants per plan (never hard-coded to plan names)
- ProviderSubscription — a provider's subscription instance; states: Pending Payment, Active, Expired, Cancelled, Suspended
- StandaloneServiceSubscription — independent ERP or E-Commerce subscription purchased outside the main plan
- SubscriptionPayment — payment tied to a subscription/renewal
- VerificationRequest — provider verification submission; states: Not Submitted, Pending, Under Review, Approved, Rejected, Expired, Suspended
- VerificationDocument / VerificationReview — supporting docs and admin review actions/notes
- GovernmentVerificationRequest / GovernmentBusinessData — Saudi commercial-registration integration request and imported official data
- Conversation / ConversationParticipant — chat thread between project owner and provider, tied to an accepted-offer flow
- Message / MessageAttachment / MessageReadStatus — chat message content, attachments, and Sent/Delivered/Read status
- Notification / NotificationTemplate / NotificationDelivery — notification instance, its template, and per-channel delivery record
- PushDevice — device registered for push notifications
- Payment / PaymentGatewayTransaction / PaymentCallback — platform payment and gateway-side transaction/callback
- Commission — UMRANY's calculated commission on a transaction
- Wallet / WalletTransaction — supplier/customer wallet balance and its immutable ledger entries
- Refund — E-Commerce refund record
- Withdrawal — wallet withdrawal request
- FinancialAdjustment — admin-only, reason-mandatory, logged wallet adjustment (always a separate ledger entry, never edits/deletes originals)
- Admin — administrative user account (distinct from end-user accounts). `preferred_language`
  (`Enums\Language`, shared with `UserProfile::preferred_language`) drives the admin dashboard's
  UI language and RTL/LTR direction — see `docs/decisions/0022-admin-dashboard-en-ar-
  localization.md`. `theme_mode` (`Enums\ThemeMode`) is the account-level half of the dark/light
  mode toggle — see `docs/decisions/0021-velzon-material-admin-theme.md`'s update note.
- Role / Permission / RolePermission / AdminRole — dynamic RBAC: action-level permissions assigned to admin-created roles (role names not hard-coded)
- AdminCrmLead / AdminCrmActivity — UMRANY's internal sales/provider-acquisition CRM (separate from ERP's provider-facing CRM)
- DynamicPage / SeoMetadata — CMS pages and SEO landing-page metadata
- Category / Subcategory / Unit — centralized master data (project/product categories, units, configurable lists), bilingual AR/EN
- SystemSetting — configurable business settings (commission, gateway fees, min withdrawal, moderation mode, upload limits, etc.) — **not built**; see `SiteSetting` below, which is a separate, narrower entity, not a partial implementation of this one.
- SiteSetting — **built**. Single-row platform branding/contact/social config (site name/title, logo, contact email/phone/address, social links keyed by `Enums\SocialPlatform`). Admin-managed via `app/Http/Controllers/Admin/SiteSettingsController` (`GET|PUT /admin/settings`, permissions `settings.view`/`settings.update` — 2 actions, not the usual 5, since it's a singleton row with no list/create/delete screen), publicly readable via `GET /api/v1/core/settings` (`Http/Controllers/SiteSettingController`) for other clients (mobile app, marketing site).
- AuditLog — immutable log of sensitive admin/financial actions
- SupportTicket / SupportTicketMessage — support ticketing (Open/In Progress/Waiting for User/Resolved/Closed)
- Integration — visibility record for configured external integrations (gov CR, payment gateway, email, push, analytics)

Reports (dashboards/exports) has no dedicated entities of its own in the source spec — it
aggregates/queries data owned by the other Core entities and by other modules.

## Key workflows

- Registration -> account verification -> default profile creation -> (optional) provider profile
  activation -> provider verification (manual and/or government CR) -> provider goes public
- Login -> session + auth token issuance -> capabilities resolved dynamically from profile,
  subscriptions, verification status, and permissions (never a fixed per-product account)
- Provider onboarding: activate profile -> company info + categories -> upload verification docs
  -> government verification (if available) -> admin review/approval -> profile published ->
  subscription-gated features activate
- Subscription purchase -> payment success (or manual admin activation) -> plan features unlock;
  ERP/E-Commerce can also be purchased standalone even outside the active main plan
- Subscription expiration -> premium access removed, but historical data and in-flight records
  (e.g., open orders) stay accessible; renewal restores functionality
- Chat: project owner receives offer -> owner initiates conversation (provider can never initiate
  first) -> messages/attachments exchanged with read-status tracking -> on offer acceptance,
  provider gains permitted contact details while the conversation stays available
- Notification dispatch: any module raises an event (offer accepted, order status change, payment
  status, verification status, etc.) -> Core resolves the user's per-channel preferences ->
  delivers in-app + push (FCM/push devices) + email, and records per-channel delivery status
- Finance: E-Commerce checkout paid to UMRANY -> platform splits into supplier amount / gateway
  fee / commission / supplier net -> every wallet movement is an immutable ledger entry; admin
  adjustments are always separate entries with mandatory reason and logged actor, never edits to
  the original record
- Admin/RBAC: super admin or permitted admin creates a role -> assigns action-level permissions
  (e.g. `providers.verify`, `refunds.approve`) -> permission set gates routes, controller actions,
  sidebar, and page-level actions for that role, with no code changes required per role

## API prefix

Current actual mount point in this repo: `/api/v1/core` (see `Modules/Core/routes/api.php`).

The source specification documents separate business API groups per sub-area (`/api/v1/auth/*`,
`/api/v1/profile/*`, `/api/v1/providers/*`, `/api/v1/subscriptions/*`, `/api/v1/verification/*`,
`/api/v1/chat/*`, `/api/v1/notifications/*`, `/api/v1/payments/*`, `/api/v1/reports/*`,
`/api/v1/admin/*`) — see `docs/modules/core.md` for the full mapping. Whether to keep the single
`/api/v1/core` prefix or split routes to match those business groups is an open decision; either
way this file and `docs/modules/core.md` are the entity source of truth.

## Module boundary

This module has NO dependencies on any other `Modules/*` package — it must stay that way. Every
other module (Projects, ECommerce, ERP, AI) depends on Core, never the reverse. Cross-module
interaction happens only through:

- `Modules\Core\Contracts\...` interfaces that other modules implement or consume, and
- `Modules\Core\Events\...` domain events that other modules listen to (or that Core listens to,
  via events other modules dispatch — but Core must never directly reference another module's
  classes, models, or services).

If you find yourself importing anything from `Modules\Projects`, `Modules\ECommerce`,
`Modules\ERP`, or `Modules\AI` inside `Modules/Core`, that is a boundary violation — stop and
reconsider the design (likely needs a new Contract or Event instead).

## What's actually implemented (not just documented)

- `Contracts/ModuleEntitlementChecker` + `Services/SubscriptionEntitlementChecker` (stub, always
  `true` for now) + `Http/Middleware/EnsureModuleEntitlement`, aliased as `module.entitlement` in
  `CoreServiceProvider::boot()`. Every business module's route group applies
  `module.entitlement:<alias>` — this is the actual mechanism that lets a customer buy ERP without
  ECommerce (see `docs/architecture/module-boundaries.md` § Independent purchasability). Wire real
  subscription logic into `SubscriptionEntitlementChecker` only — nothing else needs to change.
- `Http/Controllers/HealthController@ready` at `GET /api/v1/core/health/ready` — checks DB, Redis,
  and cache round-trip; returns 503 if any fail. `GET /api/v1/core/health` is a plain liveness
  check. Both are intentionally outside `auth:sanctum`/`module.entitlement` — monitors must be able
  to hit them unauthenticated.
- **Auth** (`Http/Controllers/AuthController`, `SessionController`, `MfaController`): register
  (mobile or email, optional `account_types` intent + `mfa_enroll`), login (two-step when MFA is
  enabled — see below), logout/logout-all, session listing + individual/all-others revocation
  (configurable expiry via `config('sanctum.expiration')`, pruned daily —
  `docs/architecture/infrastructure.md` § Scheduled tasks), self-service account deletion
  (password-confirmed, soft-deletes — `docs/decisions/0014-user-soft-deletes-and-partial-unique-
  indexes.md`), resend/verify via a 6-digit OTP code (`Models/VerificationCode`, hashed at rest,
  capped at 5 guess attempts per code), forgot/reset password via the *same* OTP mechanism rather
  than Laravel's email-only broker — deliberately, so a mobile-only account (no email on file) can
  still reset its password. `Enums/VerificationCodePurpose` (`account_verification` vs
  `password_reset`) is a separate axis from `Enums/VerificationCodeType` (the delivery channel,
  `email` vs `mobile`) — don't conflate the two when adding a new code-gated flow. Named rate
  limiters (`login`, `verification-code`, `verification-code-consume`, `password-reset`,
  `mutations`, `mfa-challenge`) are registered in `app/Providers/AppServiceProvider` and applied
  per-route in `routes/api.php` — see `docs/api/conventions.md` § Rate limiting. Account
  verification gates most-but-not-all authenticated endpoints — see `docs/decisions/0015-account-
  verification-gate-policy.md` for the exact rule and route split.
- **TOTP MFA** (`MfaController`, `Services/MfaService`, `Models/UserMfaSetting`,
  `Models/MfaRecoveryCode`): opt-in, default disabled, enrollable at registration or via profile;
  never enforced until a confirm step succeeds. A confirmed setting switches login into a two-step
  challenge/response exchange rather than issuing a token directly. See `docs/decisions/0012-totp-
  mfa-with-two-step-login.md` for the full mechanics (replay protection, challenge caching,
  recovery codes).
- **Password history** (`Services/PasswordHistoryService`, `Models/PasswordHistory`): shared by
  both `User` and `Admin` via a polymorphic table; config-driven depth
  (`core.password_policy.history_count`). Every password-validating FormRequest builds its rule
  from the single `Password::defaults()` closure in `CoreServiceProvider::boot()`, sourced from
  `config('core.password_policy.*')` — see `docs/decisions/0013-config-driven-password-policy-and-
  history.md`.
- **Profile** (`Http/Controllers/ProfileController`, `Models/UserProfile`): view/update
  (full name, address, country/city with cross-validation that the city belongs to the selected
  country, at-least-one-of-email/mobile guarded on update), language, currency. Avatar upload/
  remove re-encodes every upload via `intervention/image-laravel` (GD driver) to a configured
  width/height/format/quality (`config('core.avatar.*')`, default WebP) regardless of what was
  submitted — see `docs/decisions/0019-intervention-image-for-avatar-optimization.md`.
  `spatie/laravel-medialibrary` is available in the stack but deliberately not used here — plain
  `Storage::disk(config('core.avatar.disk'))`, since avatars don't need conversions/responsive
  variants beyond the one fixed re-encode. `Models/Country`/`City`/`Currency` are public read-only
  master data (`Http/Controllers/MasterDataController`) — writes to them are an Admin-portal
  concern, not exposed on this controller. Each of `Country`/`Currency` has a DB-backed
  `is_default` flag (`docs/decisions/0020-database-backed-platform-defaults.md`), not a config
  lookup.
- **Provider identity** (`Http/Controllers/ProviderController`, `Models/Provider`,
  `ProviderVerification`, `ProviderDocument`): activate (one per account, enforced at the DB level
  too), update, submit verification documents, upload/remove logo and cover image
  (`logo_path`/`cover_path`, `public` disk), `social_links` (JSON object keyed by
  `Enums/SocialPlatform` — unknown keys rejected at validation, not silently dropped).
  `Enums/ProviderVerificationStatus::canSubmit()` gates resubmission — only from
  `not_submitted`/`rejected`/`expired`, never while a review is already in progress or once
  approved. Government-CR integration, Business Categories, and Portfolio/Certificates/Statistics
  are not built — see `docs/modules/core.md` § Provider for the full target shape.
- **Capability resolution** (`Contracts/UserCapabilityResolver` + `Services/CapabilityService`,
  `Http/Controllers/CapabilityController` at `GET /api/v1/core/me/capabilities`): see
  `docs/architecture/module-boundaries.md` § User capability resolution for the full contract.
  `account_types` (captured registration intent — `docs/decisions/0016-account-type-intent-
  capture.md`) is echoed here but is never an authorization source.
- **Notifications** (`Http/Controllers/NotificationController`,
  `NotificationPreferenceController`, `Notifications/BaseUserNotification` and its 8 concrete
  subclasses): in-app (framework's own `DatabaseNotification`) + email built, push deferred. Every
  concrete notification checks `Services/NotificationPreferenceService::allows()` in its `via()` —
  see `docs/decisions/0017-notification-preferences-schema.md`. New-device-login detection is a
  dedicated `Models/UserDevice` table + `Services/DeviceRecognitionService`, not inferred from
  Sanctum tokens — see `docs/decisions/0018-user-device-recognition.md`. Two of the 8 events
  (`account_suspended`/`account_activated`) have notification classes but no trigger site yet — no
  admin action changes a user's status today; not built speculatively (Rule 0).
- **Admin/RBAC** (`Models/Admin`, `spatie/laravel-permission` on the `admin` guard): see
  `docs/architecture/admin-portal.md` for the full reference — the UI lives at the application
  root (`app/Http/Controllers/Admin`), not in this module, but the `Admin` model and the
  `Services/Admin/*` classes it uses (`AdminAuthService`, `AdminManagementService`, `RoleManagementService`) are Core's, backed by `Repositories/*` (see docs/architecture/backend-layering.md).
  The permission catalog and example role → permission sets live in `config/permissions.php`
  (`config('core.permissions.*')`), not hardcoded in the seeders — five actions per resource
  (`list`/`view`/`create`/`update`/`delete`, e.g. `admins.create`, `roles.delete`), added there in
  the same change that adds a `can:<permission>` route middleware (see
  `docs/decisions/0009-granular-crud-admin-permissions.md`). `core:sync-permissions` (no options)
  does a full RBAC rebuild from that config, restoring any dashboard-created role and every admin's
  role assignment afterward; `core:permissions:audit` cross-checks routes against the
  catalog/database and can `--sync` any gap it finds. `is_super_admin` is never settable through
  the dashboard/API by anyone — only `php artisan core:admin:promote-super {email} [--revoke]`
  changes it. `Modules\Core\Events\AdminPermissionsChanged` broadcasts on `private-core.admin.{id}`
  whenever an admin's role/status changes or a role they hold gets re-synced, so an
  already-signed-in admin's dashboard can surface a live refresh banner (`docs/decisions/
  0008-admin-rbac-live-refresh-via-reverb.md`). Super Admins are excluded from the admins listing
  and dashboard count (`Admin::excludingSuperAdmins()`).
- **`SiteSetting`** (`Models/SiteSetting`, `Services/Admin/SiteSettingsService`,
  `Repositories/EloquentSiteSettingRepository`): a single-row table (id=1, guaranteed by
  `Database/Seeders/SiteSettingSeeder` via `SiteSetting::current()`'s `firstOrCreate`), not a
  key-value store — deliberately narrower than, and never to be confused with, the still-not-built
  `SystemSetting` above. Logo upload/removal mirrors `ProviderService::updateLogo()`/
  `removeLogo()`'s exact store/delete-existing pattern (`public` disk). Admin screen at
  `GET|PUT /admin/settings` (`app/Http/Controllers/Admin/SiteSettingsController`, root app, not
  this module — same placement rule as every other admin screen); public read at
  `GET /api/v1/core/settings` (`Http/Controllers/SiteSettingController`) for other clients.
- **API docs**: `dedoc/scramble`, not Scribe (`docs/decisions/0023-scramble-over-scribe.md`) — a
  purely static-analysis-based tool with no live-response-capture step, so the dry-run failures
  Scribe used to hit on file-upload endpoints and two `PUT` routes with a null relation on its
  synthetic test user simply don't apply here; there is nothing equivalent to work around.

## Gotchas

- A single user account can simultaneously hold multiple roles/identities (Project Owner, Service
  Provider, E-Commerce Customer, Supplier, ERP User). Do not assume one role per user anywhere in
  profile, permission, or provider logic — capability resolution must always be dynamic (based on
  profile + subscriptions + verification + permissions), never a fixed per-account role field.
- A user account may own at most one Provider profile, but that single provider profile can offer
  multiple business categories/services at once — don't conflate "one provider profile" with "one
  service line."
- Admin roles and permissions are fully dynamic (not hard-coded enum-like roles) — permission
  checks must be action-level (`resource.action` strings) and data-driven, since new roles can be
  created without code changes. Super Admin must always retain full access regardless of role
  configuration.
- Government-verification data must never silently overwrite user-entered provider data, must be
  stored with a source reference, and integration failures must not corrupt the provider profile —
  treat the government-integration path as best-effort/additive, not authoritative-overwrite.
- Wallet/financial records are append-only: every movement is a new ledger entry; admin
  adjustments are separate entries with mandatory reason + logged actor, and original transactions
  are never edited or deleted. Don't model wallets as a single mutable balance column without a
  backing transaction log.
- Subscription expiration must not delete or hide historical data, and in-flight operational
  records (e.g., open E-Commerce orders) must remain completable even after the provider's
  subscription providing that access has expired.
- In Chat, only the project owner can initiate the first conversation on a project, and only after
  a provider has submitted an offer — a provider can never be the one to start a conversation.
  Contact details must stay hidden until offer acceptance.
- Reports/Admin sub-areas depend conceptually on "all other modules" for data — be careful not to
  let that turn into actual code-level imports from other `Modules/*` packages (see Module
  boundary above); aggregate via events, read models, or Contracts instead.
- **`User`, `Admin`, and `Provider` use Laravel 13's attribute-based `#[Fillable([...])]`, not the
  legacy `$fillable` property — and it behaves identically for mass-assignment guarding.** A
  Repository method building its own attribute array (not passing through raw request input) that
  needs to set a non-fillable column — `status`, `is_super_admin`, `user_id` on `Provider` — must
  use `Model::forceCreate()`/`$model->forceFill(...)->save()`, never `create()`/`fill()`, or the
  value is silently dropped with no error (the column falls back to its DB-level default, or stays
  null if there isn't one). This bit every one of the Auth/Admin/Provider repositories during
  initial implementation — each one now uses `forceCreate`/`forceFill` for exactly this reason;
  follow that pattern for any new Repository method that sets a field deliberately excluded from
  `#[Fillable]`. This does **not** apply inside `database/factories/*` — Eloquent factories wrap
  instantiation in `Model::unguarded()`
  internally, so `User::factory()->create(['status' => ...])` in a test works fine even though the
  equivalent `User::create([...])` in production code would not.
- **Larastan needs to be told about this module's migrations and config, or every new column reads
  as "undefined property".** `phpstan.neon`'s `databaseMigrationsPath`/`configDirectories` list
  every module's `database/migrations`/`config` explicitly — Larastan's defaults only scan the
  root `database/migrations`/`config` directories. If a new module is ever added, add its paths to
  both lists in the same change, or that module's models will fail static analysis for no reason
  related to the actual code.
- Model relation methods need PHPDoc generics (`@return HasOne<UserProfile, $this>`, not just the
  bare `HasOne` return type) for Larastan to infer the related model's properties through the
  relation — e.g. `$user->profile->full_name`. Every relation in this module follows this pattern;
  match it for new ones.
- **Partial unique indexes are how soft-delete-aware uniqueness and single-default-row
  invariants are enforced at the DB level, not just in FormRequest validation** — a
  `Rule::unique(...)->whereNull('deleted_at')` validation rule alone would still let a raw
  `deleted_at`-blind unique constraint reject a legitimate re-registration. A plain `WHERE deleted_at
  IS NULL` partial condition needs no driver branching (identical syntax on Postgres and the
  SQLite ≥3.8 the test suite runs); a boolean-literal condition like `WHERE is_default` **does**
  need branching (`WHERE is_default` on Postgres vs `WHERE is_default = 1` on SQLite). See
  `docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md` and `docs/decisions/0020-
  database-backed-platform-defaults.md`.
- **`UserMfaSetting.secret` is `encrypted` (reversible), not hashed** — TOTP verification needs the
  plaintext secret back to compute the expected code, so it's encrypted with the app's `APP_KEY`
  rather than one-way hashed like a password. This means **rotating `APP_KEY` invalidates every
  stored MFA secret** (they become undecryptable) — there is no re-encryption/migration path built
  for that today; a real `APP_KEY` rotation would need every user to re-enroll MFA. Recovery codes
  are hashed (one-way, like a password), not encrypted, since they're never read back in plaintext.
- **The capability cache key is versioned (`umrany:core:capabilities:v3:{userId}`) — bump the
  version suffix any time `Data\UserCapabilities`'s constructor shape changes, *or* the cached
  representation itself changes.** A stale cached entry under the old key being handed to code
  that expects a different shape/format has now caused a real `__PHP_Incomplete_Class`-class
  failure twice: `v1` → `v2` (adding `isProjectOwner`/`accountTypes` changed the constructor
  arity), and `v2` → `v3` (`CapabilityService::capabilitiesFor()` stopped caching the raw `Data`
  object — which is fundamentally unsafe to round-trip through PHP's native serialize, confirmed
  by hitting this exact failure live with an entry whose fields matched the current shape exactly
  — and started caching/rehydrating its plain array form via `UserCapabilities::from()` instead).
  Bump again on either kind of change to this class, not just a constructor-shape change.
