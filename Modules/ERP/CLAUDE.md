# Modules/ERP

Lightweight business management (CRM, quotations, invoices, expenses) for construction service providers — a standalone operational tool, deliberately independent of the bidding platform. Full reference: `docs/modules/erp.md`.

## Entities

- **CRM**: `crm_leads` (Name, Company, Mobile, Email, Source, Status, Assigned Employee, Notes), `crm_activities` — pipeline stages New Lead → Contacted → Meeting Scheduled → Quotation Sent → Negotiation → Won/Lost, configurable, every stage change logged.
- **Projects**: `erp_projects` (Project Name, Client, Category, Start/End Date, Budget, Status, Description), `erp_project_milestones`, `erp_project_documents` (Contracts, BOQ, Drawings, Images, PDFs, Excel).
- **Quotations**: `quotations`, `quotation_items` — states Draft → Sent → Accepted/Rejected/Expired.
- **Invoices**: `invoices`, `invoice_items` — states Draft → Issued → Partially Paid → Paid/Cancelled.
- **Expenses**: `expenses` — Category, Amount, Date, Supplier, Notes, Attachment.
- **Calendar**: `calendar_events`.

## Key workflows

- Activate ERP → create clients/leads → create internal projects → manage milestones → create quotations → generate invoices → record expenses → monitor reports.
- Lead progresses through pipeline stages; every transition is logged.
- A won Umrany bid may be manually added as an ERP project; an ERP project may equally be a purely external client engagement with no Umrany involvement at all.

## API

Mounted at `/api/v1/erp` (`Modules/ERP/routes/api.php`). Currently an empty stub group, already wrapped in `['auth:sanctum', 'module.entitlement:erp']` — a customer who hasn't subscribed to ERP gets a 402 before any controller runs (see `docs/architecture/module-boundaries.md` § Independent purchasability). See `.claude/skills/laravel-endpoint` when adding the first endpoints.

## Dependencies

- Depends on `Modules/Core` only — auth, subscription gating, notifications. No other module dependency is legal (see `docs/architecture/module-boundaries.md`).
- Explicitly **does not** depend on `Modules\Projects`. Do not `use Modules\Projects\...` from inside `Modules/ERP` for any reason — if ERP ever needs data from a Projects bid, that has to come through a Core domain event or an explicit `Modules\Projects\Contracts\...` interface, not a direct import.
- Subscription expiring mid-use is a named edge case in the source spec — check subscription state, don't assume it stays active for the life of a request/session.

## Gotcha: "Project" means two different things in this codebase

`Modules/ERP` has its own `erp_projects` table/model representing a provider's internal engagement (could originate from a won Umrany bid, added manually, or be entirely external). `Modules/Projects` has an unrelated `Project` model representing the bidding-platform listing (publish → offers → award → completion).

These are **deliberately decoupled** — not the same entity, not synced automatically, not sharing a table or FK relationship. When working in this module, "project" always means the ERP's own `erp_projects` row. Never assume an ERP project ID maps to a `Modules/Projects` project ID, and never write code that reaches into `Modules\Projects` models to "keep them in sync" — any linkage a provider makes between the two is a manual, provider-entered data point (e.g. a free-text note or client name), not a foreign key.
