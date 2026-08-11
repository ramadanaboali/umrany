# Projects Module

Project bidding platform: owners publish construction projects, providers submit competitive quotations through a transparent digital workflow, negotiate via chat, and get selected — the module ends at selection, it does not touch execution, contracts, payment, or disputes. Full reference: `docs/modules/projects.md`.

## Entities

- **Project** — a published construction requirement (title, category/subcategory, country/city, description, budget, BOQ/attachments); owned by a Project Owner; moves through Draft → Published → Receiving Offers → Partial Acceptance (optional) → Completed → Closed → Archived.
- **ProjectOffer** — a provider's quotation against a project (price, estimated duration, description, attachments, optional BOQ); states Draft → Submitted → Updated → Withdrawn → Accepted.
- **ProjectProviderAcceptance** — records that the owner accepted a given provider's offer; the event that unlocks owner contact info for that provider. A project can have more than one of these (multi-provider acceptance).
- **ProjectChat / ProjectChatMessage** — a conversation scoped to one project + one offer/provider, only owner-initiated, only after an offer exists; contact info stays hidden inside it.
- **ProjectCategory** — main category / subcategory taxonomy projects are classified and searched by.
- **ProjectFile / ProjectOfferFile** — attachments, images, documents, BOQ files on projects and offers respectively.
- **Tender / TenderDocument** and **Auction / AuctionBid** (Phase 2) — specialized project types (Public Tender, Private Tender, Auction) reusing the same Project/Offer engine with deadline-driven submission, technical/financial evaluation, or live ranking and winner selection.

## Lifecycle (happy path)

1. Owner creates project (Draft) — required: title, category, subcategory, country, city, description.
2. Owner publishes — project visible to providers per their subscription tier, unless it was created directly from a provider's profile (then visible only to that provider). AI Matching (in `Modules/AI`) notifies eligible providers.
3. Providers browse/search (filtered, subscription-gated visibility and offer limits) and view details — everything except owner name/mobile/email/WhatsApp/exact address.
4. Provider submits an offer (price, duration, description, attachments).
5. Owner may start a project-chat with that provider to negotiate; contact info stays hidden throughout. Provider can revise the offer in response.
6. Once 2+ offers exist, owner can use the manual comparison table and/or the optional AI-generated advisory summary — AI never decides, owner always makes the final call.
7. Owner accepts one or more offers. Acceptance unlocks owner contact info for that provider only; it does not auto-close the project.
8. Owner explicitly chooses: keep receiving offers, or close the project.
9. Owner marks the project completed (manual, any time) → read-only except historical viewing → eventually Archived.

There is no formal "reject offer" action in the spec — non-acceptance is the only rejection signal.

## API

Mounted at `/api/v1/projects/*` (see `Modules/Projects/routes/*.php` — currently a skeleton with no endpoints registered yet). Route/controller work should go through `.claude/skills/laravel-endpoint`.

The whole route group is already wrapped in `['auth:sanctum', 'module.entitlement:projects']` — a customer who hasn't subscribed to Projects gets a 402 before any controller runs. See `docs/architecture/module-boundaries.md` § Independent purchasability; don't duplicate that check inside individual endpoints.

## Dependencies

This module may depend on `Modules\Core` only, and only through its public surface — `Modules\Core\Contracts\...` interfaces or `Modules\Core\Events\...` domain events, never Core's Eloquent models directly. Covers:

- Auth / user identity
- Provider profiles (verification status, rating, completed-projects count — used in Offer Comparison)
- Subscriptions (gates project visibility in Search and offer-submission limits in Offers)
- Chat transport (Reverb-backed realtime delivery; Projects owns the project/offer-scoped conversation rules on top)
- Notifications (offer submitted/updated/withdrawn/accepted, chat started/message, provider accepted, project closed, tender/auction events, etc.)

Never reach into `Modules\ECommerce`, `Modules\ERP`, or `Modules\AI` Eloquent models directly. AI Matching (triggered on project creation) and AI Offer Comparison both live in `Modules/AI` and are consumed as a service contract, not reimplemented here. If you're about to `use Modules\AI\...` or any other business module's internals from here, stop and check `.claude/skills/module-boundary-check`.

## Gotchas

- **Contact info stays hidden until acceptance.** Project owner name, mobile, email, WhatsApp, and exact address must never be serialized to a provider until that specific provider has an acceptance record for the project. This applies to project details endpoints and to chat — don't leak it through either.
- **A project can accept multiple providers.** Do not assume single-winner semantics: acceptance is per-offer, not a project-level "select one" action. Accepting one provider must not implicitly close the project or reject the others.
- **Owner explicitly controls whether the project stays open after acceptance.** Don't auto-close on first acceptance; that's a separate, explicit owner action.
- **No formal offer-rejection state.** Don't invent a `rejected` status unless a future FR adds one — the spec models rejection as "not accepted" / negotiation via chat / withdrawal by the provider.
- **Manual comparison vs. AI comparison are two different features.** The Offer Comparison sub-area's criteria-based table (price, duration, rating, verification, experience, completed projects) is this module's own feature. The "AI-generated comparison summary" is a distinct capability owned by `Modules/AI` ("AI Offer Comparator") and is optional/admin-disable-able — don't conflate the two or reimplement AI scoring logic inside Projects.
- **Projects created from a provider's profile are private to that provider** — not publicly searchable like normal published projects. Check the creation path before assuming every project is public.
- **Chat can only start after an offer exists**, and only the owner can initiate it — a provider cannot open a conversation unprompted.
- **Tenders/Auctions (Phase 2) share the Project/Offer engine** rather than being separate models — don't build a parallel data model for them; extend the existing one with the tender/auction-specific tables (`tenders`, `tender_documents`, `auctions`, `auction_bids`).
