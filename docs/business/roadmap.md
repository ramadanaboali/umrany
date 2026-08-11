# Product & Engineering Roadmap

This is the answer to "what order do we build this in, and why" — grounded in the business model (`business-model.md`), the module entity/workflow docs (`docs/modules/*.md`), and the dependency structure already encoded in the codebase (`docs/architecture/module-boundaries.md`). It exists so that starting a new phase is a decision to *scope down* (pick the next phase below), not a decision to invent priorities from scratch.

## How to read this

Each phase lists: the goal, why it sits at this point in the sequence, what gets built in Core vs. the business module, and what "done" looks like to move on. Phases are the default sequential order given a single team — parallelization opportunities are called out explicitly where the dependency graph actually allows it.

Root `CLAUDE.md` Rule 0 ("bare skeleton, no business features") describes **Phase 0's** end state, not a permanent constraint — as each phase below is greenlit, that's the explicit signal Rule 0's scope has been lifted for that work. Update `CLAUDE.md` (or just state it clearly at the start of that work) when a phase starts.

## Phase 0 — Foundation ✅ (complete)

Modular monolith skeleton (5 modules), Docker/Octane/RoadRunner/Reverb/Horizon/Postgres/Redis infrastructure, the module-entitlement mechanism (currently a stub that allows everyone — see Phase 2), health checks, log viewer, the full docs/CLAUDE.md/skills system, and Postman/OpenAPI exports. Generates no revenue by itself — it's the platform every later phase builds on, and per Rule 0 nothing past this point should exist yet until a phase below is actually started.

## Phase 1 — Identity & Access

**Core sub-areas:** Auth, Profile, Provider identity, Verification (`docs/modules/core.md`).

**Why first:** every other phase needs "who is this user, are they verified, what's their profile" — it's a literal dependency of the `auth:sanctum` gate already wrapping every business module's routes, and of `Modules\Core\Contracts\ModuleEntitlementChecker`, which needs a real user to check.

**Build:** registration/login/password reset (Sanctum tokens), sessions/devices, profile CRUD, provider-profile activation + business categories, verification-document upload, manual review workflow, optional government (Saudi CR) verification integration, the admin screens needed to review/approve verification requests.

**Exit criteria:** a user can register, build a profile, activate a provider identity, and get verified — holding a real Sanctum token that every later phase's endpoints can authenticate against. Monetization is still Phase 2, so `SubscriptionEntitlementChecker` keeps returning `true` for now.

## Phase 2 — Monetization

**Core sub-areas:** Subscription, Finance/wallet primitives, payment gateway integration.

**Why second:** the business model *is* subscriptions (provider plans, standalone ERP/E-Commerce subscriptions) plus marketplace commission — "buy ERP without ECommerce" only means something once real subscriptions exist to check. This phase's exit criterion is the one piece of Phase 0 explicitly marked as a placeholder: `Modules\Core\Services\SubscriptionEntitlementChecker::hasAccess()` currently always returns `true`.

**Build:** `SubscriptionPlan`/`SubscriptionFeature`/`PlanFeature` admin configuration, `ProviderSubscription` lifecycle (Pending Payment → Active → Expired/Cancelled/Suspended), `StandaloneServiceSubscription` for ERP/E-Commerce bought independently of the main plan, `SubscriptionPayment` + payment-gateway webhook handling, base `Wallet`/`WalletTransaction` ledger primitives (E-Commerce's supplier/customer wallets build on these in Phase 5, but the ledger model itself belongs here since it's shared), admin billing dashboard.

**Exit criteria:** `SubscriptionEntitlementChecker` does a real lookup instead of returning `true`; a provider can purchase a plan, see gated features unlock, and buy ERP or E-Commerce standalone.

## Phase 3 — Project-Based Platform MVP

**Module:** `Modules/Projects` (`docs/modules/projects.md`).

**Why third:** listed first among the four products in the source analysis, and the most natural first thing Phase 2's subscription-gating earns money on (offer-submission limits, featured listings, visibility tiers).

**Build:** project CRUD + lifecycle (Draft → Published → Receiving Offers → Completed → Closed → Archived), category taxonomy, subscription-gated search/browse, `ProjectOffer` submission/withdrawal, project-scoped chat (built on Core's Chat), multi-provider acceptance (contact info hidden until acceptance — see that module's gotchas), manual offer-comparison table, completion/close, admin project moderation.

**Depends on:** Phase 1 (identity/provider/verification) + Phase 2 (subscription-gated visibility/offer limits) + Core Chat/Notification.

**Exit criteria:** a project owner can publish and receive/compare/accept offers; a provider can browse and submit offers within their subscription limits. First complete, chargeable product loop.

## Phase 4 — ERP MVP

**Module:** `Modules/ERP` (`docs/modules/erp.md`).

**Why fourth, ahead of E-Commerce:** no payment-gateway/settlement/marketplace complexity — it's CRUD plus PDF generation, which is a much smaller lift than the marketplace in Phase 5, and it's independently purchasable per the business model. That makes it the fastest second revenue stream. **Parallelization note:** ERP has almost no dependency on Projects by design (see its module doc's "Independence from Projects" section) — with two engineers, this can genuinely run alongside Phase 3 rather than after it.

**Build:** CRM leads/clients, quotations, invoices (PDF export), expense tracking, basic per-provider reporting, wired to the standalone-subscription gate from Phase 2.

**Exit criteria:** a provider who only bought ERP (no Projects, no E-Commerce) has a fully usable business-management loop.

## Phase 5 — Construction E-Commerce MVP

**Module:** `Modules/ECommerce` (`docs/modules/ecommerce.md`).

**Why fifth:** the highest-complexity product of the four — payment gateway, per-order commission/fee splitting, OTP-gated delivery confirmation, supplier *and* customer wallets, refunds. It needs Phase 2's Finance/wallet primitives solid, and benefits from Projects/ERP having already proven out the subscription-gating and Core-integration patterns in a simpler setting first.

**Build:** store setup, product/variant/category CRUD, cart/checkout with automatic multi-supplier order splitting from one payment, order lifecycle, OTP-gated delivery confirmation (settlement never fires before this), supplier wallet + configurable settlement-policy engine, refunds, customer reviews, admin settlement/dispute tooling.

**Exit criteria:** a customer can check out across multiple suppliers in one payment; each supplier gets paid out correctly, net of gateway fee and platform commission, only after delivery is OTP-confirmed.

## Phase 6 — AI Services

**Module:** `Modules/AI` (`docs/modules/ai.md`): BOQ Analysis, Intelligent Provider Matching, AI Offer Comparison.

**Why sixth:** the source spec frames AI as "a cross-platform service, not an independent application" that augments the other products — there's nothing meaningful to analyze, match, or compare until Phase 3 has real projects and offers flowing. It's advisory-only everywhere (never auto-selects an offer, never bypasses subscription limits), so it adds value on top of an already-working Projects flow rather than gating anything.

**Build:** BOQ upload → extraction pipeline (as queued jobs on the `ai-low` Horizon queue, per `docs/architecture/infrastructure.md`'s naming convention), two-directional provider↔project matching + notifications, AI-generated offer-comparison summaries. External AI/LLM provider integration is a hard dependency of all three.

**Exit criteria:** uploading a BOQ produces structured line items; publishing a project notifies a ranked set of matching providers (and vice versa); a project with 2+ offers gets an AI-generated comparison summary alongside the existing manual comparison table.

## Phase 7 — Admin portal completeness, reporting, and platform-wide surfaces

**Why not earlier, and why not "in parallel with everything":** every phase above already ships *some* admin surface as an incidental part of doing that phase's job — verification review in Phase 1, billing/plan config in Phase 2, project moderation in Phase 3, settlement/dispute tools in Phase 5. This phase is for what only makes sense once there's real data across *multiple* products to govern and report on: full dynamic RBAC (`Role`/`Permission`/`AdminRole`, action-level permissions, no hard-coded role names), cross-product dashboards and reporting, SEO/CMS (`DynamicPage`/`SeoMetadata`), centralized `SystemSetting`s, `AuditLog`, and the internal `AdminCrmLead`/`AdminCrmActivity` (UMRANY's own sales CRM — distinct from ERP's provider-facing CRM, see Core's entity list).

**Exit criteria:** an admin holding a custom, admin-created role can operate every business function above without a code deploy; cross-product dashboards exist.

## Phase 8 — Hardening & launch readiness

Per-endpoint rate limiting (currently an explicit TODO in `docs/api/conventions.md`), multi-language/multi-currency support, the government-API integration deepened beyond Phase 1's verification use case, observability/alerting beyond the log viewer, load-testing Octane/Horizon under realistic traffic shapes, a security review, configuring `log-viewer`'s `viewLogViewer` Gate for production (it's open-by-default locally — see `docs/architecture/infrastructure.md`), and a CI/CD pipeline.

## Cutting across every phase

- New endpoints/migrations/jobs in any phase go through `.claude/skills/laravel-endpoint` / `laravel-migration` / `laravel-job` as usual; every phase's work closes with `.claude/skills/docs-sync-check` so this roadmap and the module docs keep describing what's actually built, not what's merely planned.
- A phase "starting" means explicitly lifting Rule 0's scope for that work — say so, don't silently start building features.

## What would justify reordering this

This sequence isn't gospel. Three things would legitimately change it: a specific customer/pilot commitment with a hard date pulling in a later phase; team capacity for genuine parallel workstreams (Phase 1→2 is the only hard sequential dependency everything else needs — Phases 3 and 4 can run in parallel given two engineers, per Phase 4's note); or the source requirements themselves changing — the analysis this roadmap is grounded in is explicitly marked **v0.1 (Draft)**.
