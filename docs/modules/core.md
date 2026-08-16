# Core Module Reference

`Modules/Core` is the shared-platform-services module of the Umrany backend. Every other module
(Projects, ECommerce, ERP, AI) depends on it; it depends on nothing else in the application.

This document is the reference for the ten sub-areas of platform functionality that live inside
`Modules/Core`, extracted from the UMRANY requirements specification. Each section covers the
sub-area's business objective, key entities/concepts, and key functional requirements, followed by
the API group prefix as stated in the source specification.

**Implementation status**: this document describes the full target domain from the source
specification — most of it is not built yet. Auth, Profile, and Provider (manual verification
only, no government-CR integration) are implemented and tested; Admin's dynamic RBAC foundation
(`Admin`/`Role`/`Permission` on the `admin` guard, Super Admin bypass, admin/role management
screens) is implemented as a Blade dashboard at the application root, not inside this module — see
`docs/architecture/admin-portal.md`. Subscription, Verification's government-integration path,
Chat, Notification, Finance, Reports, and the rest of Admin (CMS/SEO, `SystemSetting`, `AuditLog`,
internal CRM) are still just this document's target-domain description. `Modules/Core/CLAUDE.md`'s
"Implementation status" line and "What's actually implemented" section are the quick-reference
version of this same fact — check there first.

---

## Auth (Authentication & Account Management)

**Module ID:** AUTH
**Source API group:** `/api/v1/auth/*`
**Dependencies (per source doc):** Notifications, Verification, User Profile, Subscription System
**Primary actors:** Guest, Registered User, Administrator

### Business objective

Auth is the entry point to the UMRANY ecosystem. It provides secure registration, authentication,
account recovery, session management, and identity verification, while maintaining a single unified
identity across all platform products (Project-Based Platform, Construction E-Commerce, ERP,
AI Services). A single account's capabilities are determined dynamically by profile information,
subscriptions, verification status, and assigned permissions — not by separate per-product accounts.

### Key entities

- **User** — the core account: mobile number (unique), email (unique), password (policy-enforced),
  terms acceptance, verification status, account status (see lifecycle below).
- **UserSession** — an active login session, scoped to a device; users may terminate their own
  sessions, administrators may terminate any session; expiration duration is configurable.
- **VerificationCode** — a time-limited, single-use code used for account verification and password
  reset flows; expiry is configurable.
- **PasswordReset** — the forgot-password/reset-password flow record.
- **UserDevice** — a device associated with a user, used for session tracking and "login from new
  device" detection.

### Key functional requirements

- **Registration (FR-AUTH-001):** Create an account via mobile or email. Mobile/email must be
  unique and correctly formatted; password must satisfy the security policy; Terms & Conditions
  acceptance is required. On success: account is created, verification process starts, and a
  default profile is generated.
- **Login (FR-AUTH-002):** Authenticate via mobile/email + password. Only verified accounts may
  access protected services; suspended accounts cannot log in; deleted accounts cannot be restored
  via login. Produces a secure session and an authentication token.
- **Forgot Password (FR-AUTH-003):** Secure reset via verification code (expires after a
  configurable duration, single-use); password history policy may be enforced.
- **Session Management (FR-AUTH-004):** Manage active sessions across devices; users can
  terminate their own sessions, admins can terminate any session.
- **Logout (FR-AUTH-005):** Securely terminate the active session.

### User lifecycle (account states)

```
Guest -> Registered -> Verified -> Subscribed (optional)
      -> Service Provider Verification (optional)
      -> Suspended (optional) -> Deleted
```

### Permissions summary

Guests access public content only; authenticated users access products per their subscriptions;
service providers access provider-specific modules; administrators manage users per assigned
permissions; super administrators have unrestricted access.

### Notifications generated

Registration completed, verification code sent, password changed, login from new device,
suspicious login attempt, password reset, account suspension, account activation.

---

## Profile (User Profile Management)

**Module ID:** PROFILE
**Source API group:** `/api/v1/profile/*`
**Dependencies (per source doc):** Authentication, Verification, Subscription Management
**Primary actors:** Registered User

### Business objective

A centralized profile for every registered user. A single profile lets a user participate in all
platform products without creating multiple accounts — the same account may function as a Project
Owner, Service Provider, Customer, Supplier, or ERP User depending on activities and subscriptions.
Every account has exactly one profile, and profile data is shared and updated immediately across all
platform products.

### Key entities

- **UserProfile** — one-to-one with User. Editable fields: full name, profile picture, mobile
  number, email, country, city, address, preferred language, preferred currency.
- **NotificationPreference** — per-user, per-channel, per-event-type notification opt-in/out
  (in-app, push, email), configurable independently per notification type.
- **Country / City** — master data referenced by profile address fields.

### Key functional requirements

- **View/Manage Profile (FR-PROFILE-001):** One profile per account; changes propagate
  immediately to all related modules.
- **Update Personal Information (FR-PROFILE-002):** Update the editable field set above; email
  and mobile changes require re-verification and must remain unique platform-wide.
- **Profile Picture (FR-PROFILE-003):** Upload/replace/remove; supported formats only; configurable
  max size; automatic optimization.
- **Notification Preferences (FR-PROFILE-004):** Per-channel (in-app/push/email), per-event-type
  configuration.
- **Language Preference (FR-PROFILE-005):** Arabic or English; UI switches RTL/LTR automatically
  based on selection.
- **Currency Preference (FR-PROFILE-006):** Displayed currency depends on the country currently
  enabled by platform administration (multi-currency is supported internally).

### Notifications generated

Email changed, mobile number changed, profile updated, verification required, verification
completed.

---

## Provider (Service Provider Profile)

**Module ID:** PROVIDER
**Source API group:** `/api/v1/providers/*`
**Dependencies (per source doc):** Authentication, Verification, Subscription Management
**Primary actors:** Service Provider

### Business objective

The public business identity of every construction service provider in the ecosystem — contractor,
supplier, engineering office, consultant, manufacturer, or other construction-related business.
Lets providers showcase expertise, services, experience, and credibility so project owners can
evaluate them before requesting quotations or purchasing products.

### Key entities

- **Provider** — the provider profile itself; one user account owns at most one provider profile,
  but a provider profile may offer multiple services simultaneously. Company info: name, logo,
  cover image, description, address, country, city, commercial registration number, license
  number, tax number (optional), year established, employee count, website, social links.
- **ProviderCategory / ProviderSubcategory** — one or more business categories (Contractor,
  Supplier, Engineering Office, Consultant, Manufacturer, Interior Design, Landscape, Maintenance,
  Smart Home, Steel Structure, etc.), each with subcategories.
- **ProviderDocument** — uploaded verification documents (commercial registration, business
  license, national ID, additional documents) for manual review.
- **ProviderVerification** — tracks manual and government verification status (see Verification
  section below — Provider and Verification are tightly coupled).
- **ProviderPortfolio** — a past-project showcase item: title, description, images, videos,
  completion date, project location, related category.
- **ProviderCertificate** — professional certificates, awards, memberships, official recognitions.
- **ProviderStatistics** — public stats: completed projects, active products, average rating,
  review count, years of experience, response rate, response time.

### Key functional requirements

- **Create Provider Profile (FR-PROVIDER-001):** Any registered user may activate one provider
  profile; visible publicly only after approval.
- **Company Information (FR-PROVIDER-002)** and **Business Categories (FR-PROVIDER-003)** as above.
- **Government Verification (FR-PROVIDER-004):** Integrates with the governmental commercial
  registration service using the CR number to auto-import official company info; imported data
  may require admin approval before publication.
- **Manual Verification (FR-PROVIDER-005):** Document upload for admin review; requests remain
  pending until approved/rejected.
- **Portfolio Management (FR-PROVIDER-006)** and **Certificates (FR-PROVIDER-007)** as above.
- **Public Statistics (FR-PROVIDER-008)** as above.
- **Subscription-Gated Features (FR-PROVIDER-009):** Profile dynamically shows premium features
  based on active subscription — verified badge, featured listing, ERP/E-Commerce access, max
  project offers, AI features, premium visibility.

### Business workflow

Activate provider profile -> complete company info -> select categories -> upload verification
documents -> government verification (if available) -> admin review -> profile goes public ->
subscription features activate per selected plan.

### Notifications generated

Verification submitted/approved/rejected, subscription activated/expired, profile
published/suspended, government verification completed.

---

## Subscription (Subscription Management)

**Module ID:** SUBSCRIPTION
**Source API group:** `/api/v1/subscriptions/*`
**Dependencies (per source doc):** Payments, Provider Profile
**Primary actors:** Service Provider, Administrator

### Business objective

Controls premium capabilities available to service providers **without hard-coding fixed package
structures into application code**. Administrators create subscription plans dynamically and
determine which features each plan includes.

### Key entities

- **SubscriptionPlan** — admin-configured: name, description, price, billing period, status,
  country, currency, display order.
- **SubscriptionFeature / PlanFeature** — the join between plans and dynamically configurable
  features (number of project offers, project visibility, featured search position, verified
  badge, E-Commerce access, ERP access, AI features, additional visibility features). Features are
  controlled dynamically, never hard-coded to a specific plan name.
- **ProviderSubscription** — a provider's subscription to a plan; lifecycle states: Pending
  Payment, Active, Expired, Cancelled, Suspended.
- **StandaloneServiceSubscription** — independent ERP or E-Commerce subscription purchased outside
  the main plan (see below).
- **SubscriptionPayment** — payment record tied to a subscription/renewal.

### Key functional requirements

- **Dynamic Plans (FR-SUB-001, FR-SUB-002):** Admins create/configure plans and their feature
  sets; plans apply only to service providers; free users retain permitted base functionality.
- **Independent ERP/E-Commerce Subscriptions (FR-SUB-003, FR-SUB-004):** A provider may
  subscribe to ERP and/or E-Commerce independently even when not included in their active main
  package. Access logic: if a feature is included in the active package, access is granted; if
  not, it may be purchased standalone where supported.
- **Lifecycle (FR-SUB-005, FR-SUB-006):** Subscriptions activate only after successful payment or
  manual admin activation (where permitted). On expiration: premium access is removed, but
  historical data is never deleted and in-flight operational records (e.g., open E-Commerce
  orders) remain accessible to completion; renewal restores functionality.

### Notifications generated

Subscription activated/expiring/expired, renewal successful, payment failed, ERP activated,
E-Commerce activated.

---

## Verification (Verification System)

**Module ID:** VERIFICATION
**Source API group:** `/api/v1/verification/*`
**Dependencies (per source doc):** Provider Profile, Government Integration
**Primary actors:** Service Provider, Administrator

### Business objective

Improves platform trust by validating service provider business information through two paths:
manual document review and integration with the Saudi governmental commercial registration
service.

### Key entities

- **VerificationRequest** — a provider's verification submission; states: Not Submitted, Pending,
  Under Review, Approved, Rejected, Expired, Suspended.
- **VerificationDocument** — an uploaded document (commercial registration, business license,
  identity document, additional regulatory documents).
- **VerificationReview** — admin review action/notes (approve, reject, request more documents,
  internal notes, suspend verified status).
- **GovernmentVerificationRequest / GovernmentBusinessData** — the government-integration side:
  request to the CR service and the imported official data (legal business name, CR number,
  registration status, establishment date, business activities, legal entity info, branches,
  registered address, etc.).

### Key functional requirements

- **Manual Verification (FR-VER-001, FR-VER-002):** Providers submit documents; admins with
  appropriate permissions review, approve, reject, request additional documents, add internal
  notes, or suspend previously-verified status.
- **Government Verification (FR-VER-003):** Connects to the designated Saudi government service
  via the Commercial Registration Number. Business rules: government data is stored with a source
  reference; it must never silently overwrite user-entered data; integration failures must not
  corrupt provider profiles; manual verification always remains available as a fallback.

Note: Verification is functionally the identity-trust layer underneath Provider — most of its
entities (verification requests/documents/reviews) exist specifically to gate Provider profile
publication and premium visibility.

---

## Chat (Internal Chat)

**Module ID:** CHAT
**Source API group:** `/api/v1/chat/*`
**Dependencies (per source doc):** Authentication, Projects, Offers
**Primary actors:** Project Owner, Service Provider

### Business objective

Lets project owners and providers negotiate project offers while preserving the project owner's
privacy (contact details are hidden) before an offer is accepted.

### Key entities

- **Conversation** — a chat thread tied to a project + provider offer. One project may have
  multiple independent provider conversations.
- **ConversationParticipant** — the project owner and provider(s) attached to a conversation.
- **Message** — text, images, documents, BOQ files, and other supported attachments.
- **MessageAttachment** — files attached to a message.
- **MessageReadStatus** — tracks Sent / Delivered / Read status per message.

### Key functional requirements

- **Start Conversation (FR-CHAT-001):** Only the project owner may initiate the first
  conversation, and only after the provider has submitted an offer; provider cannot initiate.
  Contact details stay hidden until offer acceptance.
- **Exchange Messages (FR-CHAT-002):** Text, images, documents, BOQ files, other attachments.
- **Message Status (FR-CHAT-003):** Sent, Delivered, Read.
- **Post-Acceptance Communication (FR-CHAT-004):** Once an offer is accepted, the provider gains
  access to permitted project owner contact details; the in-app conversation remains available
  alongside that.

---

## Notification (Notification System)

**Module ID:** NOTIFICATION
**Source API group:** `/api/v1/notifications/*`
**Dependencies (per source doc):** User Preferences
**Primary actors:** All Users, Administrator

### Business objective

Delivers important platform events through configurable communication channels: In-App, Push
Notification, and Email.

### Key entities

- **Notification** — an individual notification instance delivered to a user.
- **NotificationTemplate** — the template/content definition for a notification type.
- **NotificationPreference** — per-user, per-channel, per-event-type configuration (shared concept
  with Profile's notification preferences — likely the same underlying table/entity).
- **NotificationDelivery** — per-channel delivery record/status for a notification.
- **PushDevice** — a device registered to receive push notifications.

### Key functional requirements

- **Notification Preferences (FR-NOTIFICATION-001):** Users configure notifications by channel
  and event type.
- **Transactional Notifications (FR-NOTIFICATION-002):** Triggered by a wide range of platform
  events across modules — project published, new offer, offer accepted, new message, verification
  status, subscription status, new E-Commerce order, order status change, payment status, refund,
  withdrawal, ERP reminder, AI processing completion. (This is the mechanism other modules use to
  reach users; Core owns delivery, other modules own the triggering events.)
- **Administrative Broadcast (FR-NOTIFICATION-003):** Authorized admins send notifications to
  selected user groups/audiences, filterable by: all users, providers, customers, verified
  providers, subscription type, country/city.

---

## Finance (Payment & Wallet Management)

**Module ID:** FINANCE
**Source API group:** `/api/v1/payments/*`
**Dependencies (per source doc):** Payment Gateway
**Primary actors:** Customer, Service Provider, Supplier, Administrator

### Business objective

Manages payments related to platform services and E-Commerce transactions while maintaining
accurate supplier and customer wallet ledgers. Note: Project-Based service agreements are
explicitly **excluded** from platform payments — those are negotiated/settled outside the
platform's payment flow.

### Key entities

- **Payment** — a platform payment (subscription, ERP subscription, E-Commerce subscription, other
  paid services, or E-Commerce checkout).
- **PaymentGatewayTransaction / PaymentCallback** — the gateway-side transaction record and
  asynchronous callback handling.
- **Commission** — UMRANY's calculated commission on a transaction.
- **Wallet** — a supplier or customer wallet balance.
- **WalletTransaction** — an immutable ledger entry; every wallet movement creates a permanent
  record. Types include E-Commerce revenue, refund, withdrawal, adjustment, promotional credit,
  customer refund credit.
- **Refund** — an E-Commerce refund record.
- **Withdrawal** — a supplier/customer withdrawal request against their wallet.
- **FinancialAdjustment** — a controlled, admin-only wallet adjustment; always a separate ledger
  entry (never edits/deletes original transactions), with mandatory reason and logged actor.

### Key functional requirements

- **Subscription Payment (FR-PAY-001):** Processes payment for main subscription packages, ERP
  subscription, E-Commerce subscription, and other paid platform services.
- **E-Commerce Payment (FR-PAY-002):** Customer pays the full checkout amount to UMRANY; platform
  then computes supplier order amount, payment gateway fee, UMRANY commission, and supplier net
  amount.
- **Immutable Wallet Transactions (FR-PAY-003):** Every wallet movement is a permanent record;
  types listed above.
- **Admin Financial Adjustment (FR-PAY-004):** Reason mandatory, actor logged, original
  transactions never deleted, adjustments are separate entries.
- **E-Commerce Refund (FR-PAY-005):** Eligible cancellations refund per configured rules;
  pre-preparation cancellations may credit the customer wallet directly.
- **Financial Audit Trail (FR-PAY-006):** Complete history maintained for payments, gateway
  transactions, commissions, refunds, wallet movements, withdrawals, and manual adjustments.

---

## Reports (Reports & Analytics)

**Module ID:** REPORTS
**Source API group:** `/api/v1/reports/*`
**Dependencies (per source doc):** All Business Modules
**Primary actors:** Administrator, Service Provider (where applicable)

### Business objective

Centralized operational, commercial, financial, and platform performance visibility layer,
aggregating data across all other modules.

### Key entities/concepts

Reports appears to be a read/aggregation layer rather than an owner of primary business entities —
it queries/aggregates data owned by other modules (Users, Providers, Projects, Offers,
Subscriptions, E-Commerce, Finance). No dedicated "Reports" database tables are listed in the
source specification; report definitions below describe query/aggregation surfaces, not stored
entities. **Gap:** the source doc does not specify whether reports are computed on-demand,
materialized into summary tables, or exported via a queue — this is left to implementation.

### Key functional requirements (report categories)

- **Executive Dashboard (FR-REPORT-001):** High-level metrics — total/active users, total/verified
  providers, projects published, offers submitted/accepted, active subscriptions, subscription
  revenue, E-Commerce sales, platform commission, supplier wallet balances, pending withdrawals,
  refunds, active products, orders, ERP subscribers.
- **Project Reports (FR-REPORT-002):** By category, by city, by status, offers per project, offer
  acceptance rate, provider participation, growth over time.
- **E-Commerce Reports (FR-REPORT-003):** GMV, order count, average order value, sales by
  supplier/category/product, platform commission, refund rate, cancellation rate, supplier
  performance.
- **Subscription Reports (FR-REPORT-004):** Active/new/expired subscriptions, renewal rate,
  revenue by plan, ERP and E-Commerce subscriber counts.
- **Financial Reports (FR-REPORT-005):** Payment transactions, platform revenue, gateway fees,
  supplier payables, wallet balances, withdrawals, refunds, adjustments.
- **Export:** Authorized users may export permitted reports as Excel, CSV, or PDF.

---

## Admin (Administration Portal / RBAC)

**Module ID:** ADMIN
**Source API group:** `/api/v1/admin/*`
**Dependencies (per source doc):** All Platform Modules
**Primary actors:** Super Admin, Admin Users

### Business objective

Centralized control over the entire UMRANY ecosystem. All configurable platform operations must
be visible and manageable through administrative interfaces, gated by assigned permissions. Uses a
**dynamic RBAC model**: administrative roles are not hard-coded — permissions are defined at the
action level and assigned dynamically to admin-created roles.

### Key entities

- **Admin** — an administrative user account (distinct from platform end-user accounts).
- **Role / Permission / RolePermission / AdminRole** — the dynamic RBAC core: roles are created
  by admins with sufficient permission (e.g., Finance, Support, Sales, Operations, Marketing —
  names are examples, not fixed); permissions are granular action-level grants (e.g.
  `projects.view`, `projects.edit`, `providers.verify`, `orders.edit`, `refunds.approve`,
  `withdrawals.approve`, `reports.export`, `seo_pages.edit`). Permissions gate routes, controller
  actions, sidebar visibility, page actions, and sensitive buttons. Super Admin always retains
  full access.
- **AdminCrmLead / AdminCrmActivity** — an internal CRM dedicated to UMRANY's own sales and
  provider-acquisition pipeline (leads, lead sources, pipeline stage, follow-ups, calls, meetings,
  notes, assigned sales rep, lead status, subscription opportunity). Explicitly separate from the
  ERP module's CRM (which is provider-facing).
- **DynamicPage / SeoMetadata** — CMS pages (title, slug, content, meta title/description,
  keywords, Open Graph info, indexing settings, publication status) and structured SEO landing
  pages for services/categories/cities/providers/products.
- **Country / City / Currency** — multi-country/currency master data, supported at
  database/architecture level; admins manage active country/currency and country availability
  (initial UI may operate with a single active country).
- **Category / Subcategory / Unit** — centralized master data for project categories, product
  categories, cities, countries, units, and other configurable lists; Arabic/English values
  maintained for admin-controlled public classifications.
- **SystemSetting** — configurable business settings: platform commission, payment gateway fees,
  minimum withdrawal, settlement waiting period, project moderation mode, upload limits,
  notification configuration, subscription configuration, platform contact info, general settings.
- **AuditLog** — immutable log of sensitive admin/financial actions (administrator, action,
  module, record, previous/new value, date, IP, device info); not editable by normal admins.
- **SupportTicket / SupportTicketMessage** — centralized support ticketing (ticket number, user,
  category, priority, status, assigned admin, messages, attachments, created/resolution date);
  states: Open, In Progress, Waiting for User, Resolved, Closed.
- **Integration** — visibility record for configured external integrations (government CR
  integration, payment gateway, email services, push notification services, analytics/GA/Search
  Console).

### Key functional requirements (by area)

- **Admin Dashboard (FR-ADMIN-001):** Configurable dashboards of operational/financial/commercial/
  technical indicators, scoped by the viewing admin's permissions.
- **User Management (FR-ADMIN-002):** Manage users, provider profiles, account status, user info,
  verification status, subscriptions, activities, related projects/orders, wallets, login info.
- **Provider Management (FR-ADMIN-003):** View/edit providers, review verification, suspend/
  restore, view projects/offers/store info/subscription info.
- **Project Management (FR-ADMIN-004):** View/edit/hide/close/reopen/archive/review/approve/
  reject projects, view offers and related conversations. Configurable **Project Moderation Mode**:
  Automatic Publication, Review All Projects, or Conditional Review (with configurable conditions).
- **E-Commerce Management (FR-ADMIN-005):** Manage supplier stores, products, product categories,
  orders, payments, refunds, supplier wallets, withdrawals, platform commissions.
- **Subscription Management (FR-ADMIN-006):** Create/edit plans, configure prices/features,
  activate/deactivate plans, assign subscriptions, view history, manage independent ERP/E-Commerce
  access.
- **Financial Management (FR-ADMIN-007):** Manage payments, wallets, settlements, withdrawals,
  refunds, commissions, gateway fees, adjustments.
- **Internal Sales CRM (FR-ADMIN-008):** As described under AdminCrmLead above.
- **Dynamic RBAC (FR-ADMIN-009, FR-ADMIN-010):** Role creation (non-hard-coded names) and
  granular action-level permission management, controlling routes, controller actions, sidebar
  visibility, page actions, and sensitive buttons; new roles require no code changes; Super Admin
  always has full access.
- **CMS & SEO (FR-ADMIN-011, FR-ADMIN-012):** Dynamic page management plus structured SEO landing
  pages for services/categories/cities/providers/products.
- **Countries & Currencies (FR-ADMIN-013):** Multi-country/currency support at the data/
  architecture level, with admin control over active country/currency and availability.
- **Categories & Master Data (FR-ADMIN-014):** Centralized master data management, bilingual
  (AR/EN) for public classifications.
- **System Settings (FR-ADMIN-015):** Business-configurable settings as listed above.
- **Audit Logs (FR-ADMIN-016):** Immutable audit trail for sensitive actions.
- **Integrations Visibility (FR-ADMIN-017):** Visibility into configured external integrations.
- **Support Management (FR-ADMIN-018):** Centralized ticketing system as described above.

### Notable edge cases called out in the source doc

Admin loses permission mid-edit; role permissions change during an active admin session (handled —
see `docs/decisions/0008-admin-rbac-live-refresh-via-reverb.md`); provider
suspended while E-Commerce orders are open; product removed while paid orders on it remain active;
subscription plan deactivated while users remain subscribed; commission changes while orders are
pending; country disabled while historical records still reference it; government integration
unavailable; payment gateway callback delayed; attempt to delete financially-referenced records.

---

## Implementation note

All ten sub-areas above (Auth, Profile, Provider, Subscription, Verification, Chat, Notification,
Finance, Reports, Admin) live together in a single codebase module: `Modules/Core`. In the source
requirements specification, each sub-area was documented with its own **business** API group
prefix (e.g. `/api/v1/auth/*`, `/api/v1/profile/*`, `/api/v1/providers/*`, etc.) — those prefixes
are preserved above for traceability back to the spec.

In the actual repository, however, `Modules/Core` routes are currently all mounted under a single
prefix: **`/api/v1/core/*`** (see `Modules/Core/routes/api.php`). As implementation work begins on
this module, engineers should explicitly decide whether to:

1. Keep everything under the single `/api/v1/core` prefix (simpler routing, one route file group,
   matches the current scaffold), or
2. Split routes by sub-area to mirror the source doc's business API groups (e.g. mount Auth at
   `/api/v1/auth`, Profile at `/api/v1/profile`, etc., even though the underlying code stays in one
   `Modules/Core` package).

Either way, this document — its entity list and functional-requirement groupings per sub-area —
should be treated as the entity source of truth for `Modules/Core`, independent of whatever URL
prefix decision is made.
