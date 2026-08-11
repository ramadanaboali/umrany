# Modules/AI

Cross-platform AI capability layer — BOQ analysis, intelligent provider matching, and AI offer comparison — that augments (never replaces) human decision-making elsewhere in the platform. Full reference: `docs/modules/ai.md`.

## Capabilities

- **BOQ Analysis** — user uploads a Bill of Quantities (Excel/PDF/structured doc); AI extracts and normalizes line items (description, quantity, unit, rate, total) into structured data for review; original file is never modified.
- **AI Matching** — two-directional: ranks candidate service providers for a project, and ranks candidate projects for a service provider; advisory only, never restricts access or bypasses subscription-based limits.
- **AI Offer Comparison** — analyzes offers submitted to the same project and produces a structured comparison (price, duration, scope, missing info, advantages/concerns); never auto-selects an offer — final choice stays with the project owner.

## API

Mounted at `/api/v1/ai` (`Modules/AI/routes/api.php`). Currently an empty stub group, already wrapped in `['auth:sanctum', 'module.entitlement:ai']` — a customer who hasn't subscribed to AI features gets a 402 before any controller runs (see `docs/architecture/module-boundaries.md` § Independent purchasability). See `.claude/skills/laravel-endpoint` when adding the first endpoints.

## Dependencies

- Depends on `Modules/Core` only — auth and notification delivery (e.g. notifying a provider of a new match).
- Is **depended on by** other modules, primarily `Modules\Projects` (BOQ analysis on project documents, provider/project matching, offer comparison during quotation evaluation), and potentially `Modules\ECommerce`/`Modules\ERP` for future AI-assisted features.
- Per `docs/architecture/module-boundaries.md`, this dependency must flow through `Modules\AI\Contracts\...` interfaces that AI defines and binds in its own service provider — AI exposes contracts, it does not reach into `Modules\Projects` (or any other module's) Eloquent models to pull the data it needs. Any project/offer/provider data AI requires as input should arrive via a Core domain event or a contract the *providing* module exposes.

## Gotcha: external AI/LLM calls are slow and costly — never call them synchronously

Every capability in this module calls out to an external AI/LLM provider. That call is high-latency and has real per-call cost, so it must never run inline in the request/response cycle.

- Dispatch BOQ analysis, matching, and offer-comparison work as queued jobs — see `.claude/skills/laravel-job`.
- Per `docs/architecture/infrastructure.md`, the Horizon queue convention here is explicit: **`ai-low` — BOQ analysis / offer comparison background jobs.** Use that queue (or an appropriately-prioritized `ai-<priority>` queue) rather than defaulting to Laravel's `default` queue.
- Set deliberate `$tries`/`$backoff` on these jobs — "AI service temporarily unavailable" is a named edge case in the source spec, so retries must be handled explicitly, not left at Laravel's defaults.
- Return a request/processing record immediately (e.g. `ai_requests` row) and let the controller/endpoint respond with a pending state; notify the user via Core notifications once the job completes.
