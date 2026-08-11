Shared-platform-services module: identity, provider profiles, subscriptions, verification, chat,
notifications, finance/wallets, reports, and admin/RBAC for the whole UMRANY platform.

See `docs/modules/core.md` for the full reference (business objectives, FR groupings, source-doc
API prefixes per sub-area).

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
