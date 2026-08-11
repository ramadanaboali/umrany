# Projects Module

Source: internal requirements specification, section 3.4 ("Project-Based Platform"), FR-PROJECT-001 through FR-PROJECT-009.

## Module overview

| Property | Value |
|---|---|
| Module ID | PROJECT |
| Priority | Critical |
| Complexity | Very High |
| Primary Actors | Project Owner, Service Provider |
| Dependencies (per spec) | Authentication, Provider Profile, Chat, Notifications, AI Matching, Subscription Management |
| Related Modules (per spec) | Offers, AI Comparator, ERP |
| API Group (per spec) | `/api/v1/projects/*` |

The Project-Based Platform is UMRANY's core procurement module. It lets project owners publish construction projects, receive competitive quotations from qualified service providers, compare proposals, communicate with providers, and select participants through a transparent digital workflow. Projects may represent any construction-related requirement: contracting services, engineering consultation, supervision, maintenance, interior design, landscaping, infrastructure, or other construction activity.

### Explicit scope boundary

The platform acts **solely as a facilitator** between project owners and service providers. Per the source spec, this module does **not** participate in:

- Contractual agreements between owner and provider.
- Project execution (the actual construction/service work).
- Financial settlement or payment between the parties.
- Legal disputes or dispute resolution between the parties.

Everything in this module ends at "the owner selects a provider and both sides have each other's contact details." What happens after that (contract signing, on-site delivery, invoicing, payment, disputes) is out of scope for `Modules/Projects` by design.

---

## Lifecycle

Module ID: `PROJECT-LIFECYCLE`. Priority Critical, Complexity High. Primary actor: Project Owner. Depends on Projects, Offers; related to Notifications, Chat.

**Business objective**: define the complete journey of a construction project from creation to closure, while keeping the flexibility to accept multiple service providers when required.

**States** (FR-PROJECT-001), each transition owner-controlled:

```
Draft → Published → Receiving Offers → Partial Acceptance (optional) → Completed → Closed → Archived
```

**Business rules**:

- Draft projects are visible only to their owner.
- Published projects become visible to providers according to subscription rules (see Search, below).
- Projects remain open until manually closed by the owner — there is no automatic closing deadline in the core lifecycle (an optional "Offer Closing Date" field exists at creation, but closing is owner-driven).
- Multiple providers may be accepted for the same project; accepting one does not force closure.
- The owner decides, after any acceptance, whether to keep receiving offers or close the project.
- Closed projects stop receiving new offers.
- Archived projects are retained for historical reference only (read-only).

## Create

Module ID: `PROJECT-CREATE`. Priority Critical, Complexity High. Primary actor: Project Owner.

**Business objective**: let owners publish a project quickly, keeping advanced fields optional so creation friction stays low.

**Key entities / fields** (FR-PROJECT-002):

- Required: Project Title, Main Category, Subcategory, Country, City, Project Description.
- Optional: Budget, BOQ File, Images, Documents, Attachments, Project Location, Expected Duration, Required Completion Date, Offer Closing Date, Additional Notes.

**Business rules**:

- Only authenticated users may publish projects.
- Required fields must be completed; advanced fields stay optional.
- Projects are publicly visible after publication, **unless** created directly from a provider's profile — those are visible only to that one provider (a direct/private request-for-quote flow).
- Uploaded files must satisfy platform-wide file restrictions.

**Post-conditions**: project created; AI Matching process starts automatically (cross-reference to `Modules/AI` — matching logic itself is out of scope here); eligible providers receive notifications.

## Details

Module ID: `PROJECT-DETAILS`. Priority High, Complexity Medium. (Primary actors/dependencies not stated in the source table for this sub-section.)

**Business objective**: expose full project information to providers while protecting the owner's privacy until a quotation is accepted.

**Key entity concept**: a visibility split between "public project data" and "owner contact data" (FR-PROJECT-003):

- Visible to authorized providers: Title, Description, Category, Subcategory, Budget (if provided), Attachments, BOQ, Images, City, Publishing Date, Remaining Offer Period, Number of Submitted Offers.
- Hidden until acceptance: Owner Name, Mobile Number, Email Address, Exact Address, WhatsApp Number.

These hidden fields become visible to a provider only after the owner accepts that provider's offer (see Completion, below).

## Search

Module ID: `PROJECT-SEARCH`. Priority High, Complexity Medium.

**Business objective** (implicit from the FR): let providers discover relevant published projects through filtering, gated by subscription tier.

**Key workflow** (FR-PROJECT-004): providers browse/search projects using filters — Country, City, Category, Subcategory, Budget Range, Date Posted, Project Status, Offer Deadline.

**Business rules**:

- Free-tier providers may have limited project visibility (subscription-gated).
- Premium subscriptions increase both project visibility and offer-submission limits.
- Search results may be enhanced with AI recommendations (cross-reference to `Modules/AI`; not detailed further in this section of the spec).

## Offers

Module ID: `PROJECT-OFFERS`. Priority Critical, Complexity High.

**Business objective**: let qualified service providers submit competitive quotations against published projects.

**Key entities**:

- **Offer** — a provider's quotation for a project: Price, Estimated Duration, Description, Attachments, BOQ File (optional), Supporting Documents.
- **Offer states** (FR-PROJECT-005): `Draft → Submitted → Updated → Withdrawn → Accepted`.

**Business rules**:

- Offer limits (how many a provider can submit, or per-project) depend on the provider's active subscription tier.
- Providers may edit or withdraw an offer any time before acceptance.
- Project owners **cannot reject offers directly** — there is no explicit "reject" action. Negotiation happens through the internal chat, the provider updates the offer in response, and the owner accepts the version they want. (Implicit rejection = simply not accepting; the spec defines no formal reject state or endpoint.)

**Notifications**: Offer Submitted, Offer Updated, Offer Withdrawn, Offer Accepted, New Chat Message.

**Database tables (per spec)**: `projects`, `project_files`, `project_offers`, `project_offer_files`, `project_categories`, `project_provider_acceptance`.

**API endpoints listed in spec** (illustrative, relative to `/api/v1/projects`): `GET /projects`, `POST /projects`, `PUT /projects/{id}`, `GET /projects/{id}`, `POST /projects/{id}/offers`, `PUT /offers/{id}`, `DELETE /offers/{id}`, `POST /offers/{id}/accept`, `GET /projects/search`.

**Edge cases called out in spec**: subscription expires mid-submission; provider edits offer mid-negotiation; project closes while an offer edit is in flight; multiple providers accepted on one project; owner accepts one provider but keeps the project open for more offers; owner closes the project after a partial acceptance; provider withdraws an offer immediately before acceptance (race condition).

## Offer Comparison

Module ID: `PROJECT-OFFER-COMPARISON`. Priority High, Complexity Medium. Primary actor: Project Owner. Depends on Project Offers, AI Offer Comparator; related to Chat, Notifications.

**Business objective**: give owners a structured side-by-side comparison of submitted quotations to simplify evaluation.

**Key workflow** (FR-PROJECT-006): compare offers on Offered Price, Estimated Duration, Provider Rating, Provider Verification Status, Company Experience, Number of Completed Projects, Attachments, Offer Description.

**AI comparison**: the platform can generate an AI summary of each quotation's strengths/weaknesses — advisory only, it never decides on the owner's behalf. This AI capability is a distinct concern owned by `Modules/AI` ("AI Offer Comparator" in the dependency list); `Modules/Projects` only surfaces the comparison, it does not implement AI scoring itself.

**Business rules**:

- Comparison view only activates once two or more offers exist on a project.
- AI comparison is optional and can be disabled platform-wide by administrators.
- The project owner always makes the final acceptance decision — the manual, criteria-based comparison table and the optional AI-generated summary are two different aids toward the same owner decision, not two competing features.

## Chat

Module ID: `PROJECT-CHAT`. Priority Critical, Complexity High.

**Business objective**: enable secure negotiation between owner and provider before acceptance, without exposing private contact information.

**Key entities**: conversation tied to a project + offer/provider pair; messages with attachments.

**Business rules** (FR-PROJECT-007):

- Only the project owner can initiate a conversation.
- A conversation can only start after the owner has received an offer (i.e., chat is offer-scoped, not open-ended messaging).
- Contact information stays hidden for the duration of the conversation (consistent with Details' hidden-fields rule).
- The provider may revise their quotation as a result of the negotiation; the owner may then accept the updated quotation.

**Supported features**: text messages, image attachments, file attachments, BOQ sharing, read status, delivery status.

**Relationship to Core**: real-time transport (WebSocket/Reverb, generic message delivery) is owned by `Modules/Core`'s chat infrastructure per the system architecture doc; `Modules/Projects` owns the project/offer-scoped conversation semantics and business rules layered on top (who can start a thread, when contact info unlocks), consuming Core's chat transport rather than re-implementing it.

## Completion

Module ID: `PROJECT-COMPLETION`. Priority High, Complexity Medium.

**Business objective**: let owners manage accepted providers while keeping the option to continue receiving offers.

**Key workflow — Accept Provider** (FR-PROJECT-008):

- Owners can accept one or more providers for the same project.
- Accepting a provider does **not** automatically close the project.
- After any acceptance, the owner explicitly decides: continue receiving offers, or close the project immediately.
- Accepted providers gain access to the owner's contact information (this is the trigger that lifts the Details hidden-fields restriction for that specific provider).
- Providers who are not (yet) accepted keep seeing the project as long as it stays open.

**Project completion**: owners can mark a project completed manually at any time. Completed projects stop receiving new quotations and become read-only except for historical viewing. (The spec does not describe an automatic/system-triggered completion path — completion is always an owner action.)

## Tenders (Auctions & Tenders)

Module ID: `PROJECT-TENDERS`. Priority Medium, Complexity High. Status: Phase 2 (not MVP).

**Business objective**: support public/private tenders and auction-based procurement on the same underlying project engine, with a dedicated UX per type.

**Key entities / project types** (FR-PROJECT-009): Standard Project, Public Tender, Private Tender, Auction.

**Tender-specific features**: Tender Documents, BOQ Distribution, Submission Deadline, Opening Date, Technical Evaluation, Financial Evaluation.

**Auction-specific features**: Auction Start Date, Auction End Date, Live Ranking, Automatic Winner Selection, Manual Winner Approval.

**Business rules**:

- Tenders and Auctions get independent user interfaces but share the same underlying project engine (i.e., they extend the core Project/Offer model rather than being a separate system).
- Private tenders can be access-restricted.
- Submission deadlines are mandatory for tenders.
- Auction rules (e.g., how ranking/winner-selection behaves) are administrator-configurable.

**Additional notifications introduced by this sub-area**: New Project Published, New Offer Received, Offer Updated, Chat Started, New Chat Message, Provider Accepted, Project Closed, Tender Published, Tender Closing Soon, Auction Started, Auction Ending Soon.

**Database tables (per spec, superset covering standard projects plus tenders/auctions)**: `projects`, `project_types`, `project_categories`, `project_files`, `project_offers`, `project_offer_files`, `project_chats`, `project_chat_messages`, `project_provider_acceptance`, `tenders`, `tender_documents`, `auctions`, `auction_bids`.

**API endpoints listed in spec** (illustrative): `GET /projects`, `POST /projects`, `PUT /projects/{id}`, `DELETE /projects/{id}`, `GET /projects/search`, `POST /projects/{id}/offers`, `PUT /offers/{id}`, `DELETE /offers/{id}`, `POST /offers/{id}/accept`, `POST /projects/{id}/chat`, `GET /projects/{id}/messages`, `POST /projects/{id}/close`, `POST /projects/{id}/reopen`, `GET /tenders`, `POST /tenders`, `GET /auctions`, `POST /auctions`.

**Edge cases called out in spec**: owner accepts multiple providers simultaneously; owner closes a project while active negotiations exist; provider withdraws an offer after negotiation; offer limit reached per subscription.

---

## End-to-end lifecycle (all sub-areas combined)

1. **Publish** — an authenticated owner creates a project (Create) with required fields; optional fields (budget, BOQ, attachments, dates) can follow later. The project starts in `Draft`, visible only to the owner.
2. **Go live** — owner publishes; project moves to `Published`, becomes publicly visible (unless it was created directly from a provider's profile, in which case only that provider sees it). AI Matching runs and notifies eligible providers.
3. **Browse / subscription-gated visibility** — other providers discover the project via Search, filtered and ranked, with breadth of visibility and offer-submission limits controlled by their subscription tier (Search).
4. **View details, contact hidden** — a provider opens the project and sees full project data except the owner's identity/contact fields, which stay hidden (Details).
5. **Submit quotation** — an eligible provider submits an Offer (price, duration, description, attachments); project state moves toward `Receiving Offers`. Owner and other providers are notified.
6. **Negotiate via chat** — once an offer exists, the owner may start a project-scoped chat with that provider. Contact info remains hidden throughout. The provider can revise the offer based on the conversation (Chat + Offers interplay).
7. **Compare** — once two or more offers exist, the owner can use the manual comparison table (price, duration, rating, verification, experience, completed-projects count) and, optionally, an AI-generated advisory summary (Offer Comparison). The AI comparison never decides for the owner.
8. **Accept one or more providers** — the owner accepts one or multiple offers (`Partial Acceptance` is a first-class, optional lifecycle state). Accepted providers immediately gain the owner's contact information (Completion).
9. **Owner controls whether the project stays open** — accepting a provider does not auto-close the project; the owner explicitly chooses to keep receiving offers or to close immediately.
10. **Completion / closure** — the owner can mark the project completed manually at any point. Completed/closed projects stop accepting new offers and become read-only.
11. **Archive** — the project eventually reaches `Archived`, retained for historical reference only.

Tenders and Auctions (step 9 onward, for that project type) reuse the same engine but add deadline-driven submission windows, technical/financial evaluation (tenders), or live ranking with automatic/manual winner selection (auctions) — see Tenders above.

Everything above stops at "owner and accepted provider now have each other's contact details and a project record showing who was selected." Contract signing, on-site execution, invoicing/payment, and dispute handling are explicitly out of this module's scope (see Explicit scope boundary, above).

## Known gaps in the source spec

- No formal "reject offer" state/endpoint is defined; rejection is implicit (owner simply doesn't accept, or the project closes).
- Automatic/system-triggered project completion (e.g., time-based) is not described — completion is always an explicit owner action per this spec.
- AI Matching (triggered on project creation) and AI-driven search ranking are referenced only as dependencies/cross-references; their internal logic belongs to `Modules/AI` and is not detailed within the Projects sections of the source document.
- `PROJECT-DETAILS`'s Module Information table in the source omits a stated Primary Actors/Dependencies row (unlike the other sub-sections); documented here as given.
