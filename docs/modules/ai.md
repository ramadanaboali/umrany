# AI Module

Source: Umrany System Analysis & Software Requirements Specification, Section 3.7 ("Artificial Intelligence Services"), Section 5.11–5.13 (AI workflow diagrams).

## Module Information

| Property | Value |
|---|---|
| Priority | High |
| Complexity | Very High |
| Primary Actors | Project Owner, Service Provider |
| Dependencies | Projects, Offers, BOQ Files, Provider Profiles |
| Related Modules | Project-Based Platform, Notifications |
| API Group | `/api/v1/ai/*` |

## Business Objective

Artificial Intelligence services are embedded within UMRANY to reduce manual analysis, improve matching quality, and support better decisions across project procurement workflows.

The AI layer consists of three primary services: BOQ Analysis, AI Matching, and AI Offer Comparison.

Per `docs/business/overview.md`, AI is one of the platform's four core products but is explicitly **"integrated as a cross-platform service rather than an independent application"** — it has no end-user-facing surface of its own comparable to the bidding platform or marketplace; it augments workflows that live in other modules. Every business rule in the source spec reinforces that AI output is advisory: it does not auto-accept offers, does not restrict project visibility, and does not replace the project owner's final decision. AI augments, it does not replace, human decision-making.

## Sub-services

### 3.7.1 BOQ Analysis (FR-AI-001, FR-AI-002)

**What it does**: users upload a Bill of Quantities document; the AI extracts and normalizes construction line items into structured data for review.

Supported inputs: Excel files, PDF files, structured BOQ documents.

Process:
1. User uploads BOQ.
2. System validates the file.
3. AI extracts construction items.
4. Items are normalized into structured data.
5. Quantities, units, descriptions, and related information are identified.
6. Analysis results are displayed to the user.
7. User may review or correct extracted information.

Extracted data may include: Item Number, Item Description, Category, Quantity, Unit, Unit Rate, Total Amount, Section, Notes.

Business rules:
- Original uploaded file shall remain available.
- Extracted output shall be stored separately (never overwrites the source file).
- Users shall be able to review extraction results.
- AI results shall not modify original source files.

**Consumer**: primarily `Modules/Projects` — BOQ upload/analysis is tied to project procurement (per the module's own "Dependencies: Projects, BOQ Files"). The source does not spell out the exact call path from Projects into AI; treat "feeds Projects" as the intent, with the concrete contract left to implementation.

### 3.7.2 AI Matching (FR-AI-003, FR-AI-004)

**What it does**: recommends suitable service providers for a project, and recommends relevant projects to service providers — a two-directional matching service.

Matching inputs: Project Category, Subcategory, Location, Provider Categories, Provider Experience, Verification Status, Ratings, Previous Activities, Relevant Projects, Provider Availability (where applicable).

Output: ranked provider recommendations for the project owner (FR-AI-003); ranked project recommendations for service providers (FR-AI-004).

Business rules:
- Recommendations do not restrict access to other visible projects.
- AI ranking is advisory.
- Subscription rules continue to control project access and offer limits — matching never bypasses subscription gating.
- Matching results may be recalculated when project or provider data changes.

**Consumer**: connects `Modules/Projects` projects with providers in both directions (project → candidate providers, and provider → candidate projects).

### 3.7.3 AI Offer Comparator (FR-AI-005)

**What it does**: analyzes offers submitted to the same project and generates a structured comparison to support the project owner's decision.

Comparison inputs: Price, Duration, Offer Description, Provider Profile, Provider Rating, Verification Status, Uploaded Documents, BOQ Information (where available).

Comparison output: Price Comparison, Duration Comparison, Scope Differences, Missing Information, Key Advantages, Potential Concerns, Structured Summary.

Business rules:
- AI shall not automatically accept an offer.
- Final selection remains entirely with the project owner.
- Original offer information shall always remain accessible.
- Generated summaries shall clearly remain advisory.

**Consumer**: feeds `Modules/Projects`' offer/quotation evaluation step — the project owner reviews the AI-generated comparison alongside the raw offers before selecting a provider.

## Key Entities

Database tables named in the source:

- `ai_requests`
- `ai_boq_analyses`
- `ai_boq_items`
- `ai_matching_results`
- `ai_offer_comparisons`
- `ai_processing_logs`

## API

Group prefix: `/api/v1/ai/*` (mounted in this repo via `Modules/AI/routes/api.php`, currently an empty stub group).

Endpoints listed in the source:

- `POST /ai/boq/analyze`
- `GET /ai/boq/{id}`
- `GET /ai/projects/{id}/providers`
- `GET /ai/providers/{id}/projects`
- `POST /ai/projects/{id}/compare-offers`
- `GET /ai/projects/{id}/offer-comparison`

## Workflows (Sections 5.11–5.13)

**AI BOQ Workflow** — Actors: Project Owner, Service Provider.
1. Upload BOQ.
2. AI extracts items.
3. Structured BOQ is generated.
4. User reviews extracted data.
5. Results are used within the project.

**AI Matching Workflow** — Actors: Project Owner, Service Provider.
1. New project is published.
2. AI analyzes project information.
3. Suitable providers are ranked.
4. Recommendations are displayed.
5. Notifications are sent.

**AI Offer Comparison Workflow** — Actor: Project Owner.
1. Multiple offers are received.
2. User requests comparison.
3. AI analyzes quotations.
4. Comparison summary is generated.
5. User selects the preferred provider.

The end-to-end chapter-5 platform journey for AI Services is summarized as: Upload BOQ → AI Analysis → Provider Matching → Offer Comparison → Decision Support.

## Edge Cases (from source)

- Unsupported BOQ file.
- Poorly formatted BOQ.
- Empty document.
- Duplicate BOQ upload.
- Partial extraction.
- Provider has insufficient information for matching.
- Project receives only one offer.
- AI service temporarily unavailable.
- Offer changes after comparison was generated.

## Architectural note: service contracts, not direct model access

Per `docs/architecture/module-boundaries.md`, `Modules/AI` is one of the four business modules and is subject to the same hard rule: it must not reach into `Modules/Projects`' (or any other module's) Eloquent models directly. Given that other modules depend on AI's output (matching, comparison, BOQ extraction), the module boundary in practice runs the other way as well — AI should expose `Modules\AI\Contracts\...` interfaces that `Modules/Projects` (and potentially `Modules/ECommerce`/`Modules/ERP`) type-hint and consume, rather than AI importing those modules' models to pull the data it needs. Where AI genuinely needs project/offer/provider data as input, that should arrive via a Core domain event or a contract the *providing* module exposes — not via AI reaching outward.

## Gaps / Not Specified in Source

- No detail on which specific LLM/AI provider is used, prompt design, or model versioning.
- No specification of the exact contract interface names/method signatures for cross-module consumption — only that AI "exposes service contracts other modules use" (per root `CLAUDE.md`).
- No SLA/latency figures for "AI service temporarily unavailable" handling beyond it being a named edge case.
- No detail on how recalculation of matching results (FR-AI-004) is triggered (event-driven vs. scheduled vs. on-demand).
