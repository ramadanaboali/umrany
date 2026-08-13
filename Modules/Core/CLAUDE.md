Shared-platform-services module: identity, provider profiles, subscriptions, verification, chat,
notifications, finance/wallets, reports, and admin/RBAC for the whole UMRANY platform.

See `docs/modules/core.md` for the full reference (business objectives, FR groupings, source-doc
API prefixes per sub-area).

**Implementation status**: Auth, Profile, Provider identity/verification (manual documents only —
no government-CR integration yet), and Admin/RBAC are built and tested (see "What's actually
implemented" below). Subscription, Chat, Notification, Finance/Wallet, Reports, CMS/SEO,
`SystemSetting`, `AuditLog`, and the internal sales CRM are still just the target-domain
description below, not implemented — this is a phased build per `docs/business/roadmap.md`, not a
gap to fill speculatively.

## Entities

- User — core account (mobile/email unique, password, terms acceptance, verification/account status)
- UserSession — active login session per device; user- or admin-terminable, configurable expiry
- VerificationCode — single-use, time-limited code for account verification / password reset
- PasswordReset — forgot-password/reset-password flow record
- UserDevice — device tied to a user, used for session and new-device-login detection
- UserProfile — one-to-one profile: name, avatar, mobile, email, country, city, address, language, currency
- NotificationPreference — per-user/per-channel/per-event-type opt-in (in-app, push, email)
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
- Admin — administrative user account (distinct from end-user accounts)
- Role / Permission / RolePermission / AdminRole — dynamic RBAC: action-level permissions assigned to admin-created roles (role names not hard-coded)
- AdminCrmLead / AdminCrmActivity — UMRANY's internal sales/provider-acquisition CRM (separate from ERP's provider-facing CRM)
- DynamicPage / SeoMetadata — CMS pages and SEO landing-page metadata
- Category / Subcategory / Unit — centralized master data (project/product categories, units, configurable lists), bilingual AR/EN
- SystemSetting — configurable business settings (commission, gateway fees, min withdrawal, moderation mode, upload limits, etc.)
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
- **Auth** (`Http/Controllers/AuthController`, `SessionController`): register (mobile or email),
  login, logout, single-device-revoke session listing, resend/verify via a 6-digit OTP code
  (`Models/VerificationCode`, hashed at rest, capped at 5 guess attempts per code), forgot/reset
  password via the *same* OTP mechanism rather than Laravel's email-only broker — deliberately, so
  a mobile-only account (no email on file) can still reset its password. `Enums/VerificationCodePurpose`
  (`account_verification` vs `password_reset`) is a separate axis from `Enums/VerificationCodeType`
  (the delivery channel, `email` vs `mobile`) — don't conflate the two when adding a new
  code-gated flow. Named rate limiters (`login`, `verification-code`,
  `verification-code-consume`, `password-reset`) are registered in `app/Providers/AppServiceProvider`
  and applied per-route in `routes/api.php` — see `docs/api/conventions.md` § Rate limiting.
- **Profile** (`Http/Controllers/ProfileController`, `Models/UserProfile`): view/update
  (full name, address, country/city with cross-validation that the city belongs to the selected
  country, at-least-one-of-email/mobile guarded on update), avatar upload/remove
  (`spatie/laravel-medialibrary` is available in the stack but not used here — plain
  `Storage::disk('public')`, since avatars don't need conversions/responsive variants), language,
  currency. `Models/Country`/`City`/`Currency` are public read-only master data
  (`Http/Controllers/MasterDataController`) — writes to them are an Admin-portal concern, not
  exposed on this controller.
- **Provider identity** (`Http/Controllers/ProviderController`, `Models/Provider`,
  `ProviderVerification`, `ProviderDocument`): activate (one per account, enforced at the DB level
  too), update, submit verification documents, upload/remove logo and cover image
  (`logo_path`/`cover_path`, `public` disk), `social_links` (JSON object keyed by
  `Enums/SocialPlatform` — unknown keys rejected at validation, not silently dropped).
  `Enums/ProviderVerificationStatus::canSubmit()` gates resubmission — only from
  `not_submitted`/`rejected`/`expired`, never while a review is already in progress or once
  approved. Government-CR integration, Business Categories, and Portfolio/Certificates/Statistics
  are not built — see `docs/modules/core.md` § Provider for the full target shape.
- **Capability resolution** (`Contracts/UserCapabilityResolver` + `Services/CapabilityResolver`,
  `Http/Controllers/CapabilityController` at `GET /api/v1/core/me/capabilities`): see
  `docs/architecture/module-boundaries.md` § User capability resolution for the full contract.
- **Admin/RBAC** (`Models/Admin`, `spatie/laravel-permission` on the `admin` guard): see
  `docs/architecture/admin-portal.md` for the full reference — the UI lives at the application
  root (`app/Http/Controllers/Admin`), not in this module, but the `Admin` model and the
  `Services/Admin/*` classes it uses (`AdminAuthService`, `AdminManagementService`, `RoleManagementService`) are Core's, backed by `Repositories/*` (see docs/architecture/backend-layering.md).
  The permission catalog and example role → permission sets live in `config/permissions.php`
  (`config('core.permissions.*')`), not hardcoded in the seeders — add a permission there in the
  same change that adds a `can:<permission>` route middleware. `core:sync-permissions` (no
  options) does a full RBAC rebuild from that config, restoring any dashboard-created role and
  every admin's role assignment afterward; `core:permissions:audit` cross-checks routes against
  the catalog/database and can `--sync` any gap it finds.
- **Known minor gap**: `php artisan scribe:generate` fails its live-example dry run for `PUT
  /profile`, `PUT /providers/me`, and every file-upload endpoint (`POST /profile/avatar`,
  `POST /providers/me/logo`, `POST /providers/me/cover`) — the first two hit a real null
  `UserProfile`/`Provider` relation on Scribe's synthetic test user; the file-upload ones fail on
  `fopen((binary))` while trying to turn the doc-block's `Example: (binary)` placeholder into a
  literal file. Docs still generate fully (route list, validation rules, doc-blocks); only the
  auto-captured example *response* for these is missing. Not a bug in the endpoints themselves —
  confirmed working via Pest tests and manual testing with real data.

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
