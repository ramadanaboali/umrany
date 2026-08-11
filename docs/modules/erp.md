# ERP Module

Source: Umrany System Analysis & Software Requirements Specification, Section 3.6 ("ERP"), Section 5.9 ("ERP Workflow").

## Module Information

| Property | Value |
|---|---|
| Priority | High |
| Complexity | High |
| Primary Actors | Service Provider |
| Dependencies | Authentication, Subscription Management |
| Related Modules (conceptual, not code dependencies) | Projects, CRM, Quotations, Invoices, Expenses |
| API Group | `/api/v1/erp/*` |

## Business Objective

The ERP Platform provides a lightweight business management solution for construction service providers. It operates independently from the marketplace and enables providers to organize their daily operations through a simple cloud-based system.

The ERP is intentionally designed to remain simple, focusing only on essential operational modules without becoming a full enterprise ERP.

## Independence from `Modules/Projects`

This is a load-bearing design decision, not an implementation detail: **projects tracked inside the ERP are completely independent from the Project-Based Platform (`Modules/Projects`)**, which handles Umrany's project bidding/procurement workflow (publish → offers → chat → award → completion).

- A service provider may manually create an ERP project that *originated* from a bid won on the Umrany marketplace, or from an entirely external client relationship that has nothing to do with Umrany.
- Per the source: "ERP projects are independent from Marketplace projects," "Marketplace projects may be manually added," and "External projects may be created directly."
- The ERP must function as a standalone business tool. It has no hard dependency on `Modules/Projects` — a provider with zero Umrany-won bids can still use the ERP purely as an internal CRM/invoicing/expense tool for their offline business.
- Any link between an ERP project and a Projects-module bid is a manual, provider-initiated association, not an automatic sync or foreign key relationship into `Modules/Projects`' schema.

## Sub-sections

### 3.6.1 Dashboard (FR-ERP-001)

Quick overview of operational activity. Widgets:

- Active Projects
- Pending Quotations
- Outstanding Invoices
- Monthly Expenses
- New Leads
- Recent Activities
- Upcoming Tasks
- Calendar Events

### 3.6.2 CRM (FR-ERP-002, FR-ERP-003)

**Lead Management** — providers manage sales opportunities through a simplified CRM. Lead information: Name, Company, Mobile, Email, Source, Status, Assigned Employee, Notes.

**CRM Pipeline** — supported stages: New Lead, Contacted, Meeting Scheduled, Quotation Sent, Negotiation, Won, Lost.

Business rules:
- Pipeline stages are configurable.
- Every stage change is logged.
- Leads may contain unlimited notes and activities.

### 3.6.3 Project Management (FR-ERP-004, FR-ERP-005, FR-ERP-006)

**Internal Projects** — providers manage both UMRANY projects (won bids, manually added) and external projects inside the ERP. Project information: Project Name, Client, Category, Start Date, End Date, Budget, Status, Description.

Business rules: see "Independence from `Modules/Projects`" above.

**Milestones** — projects support milestone management. Milestone information: Title, Due Date, Budget, Status, Progress, Notes.

**Project Documents** — providers may upload documents related to internal projects: Contracts, BOQ, Drawings, Images, PDFs, Excel Files.

### 3.6.4 Quotations (FR-ERP-007)

Providers generate quotations for clients. Quotation information: Client, Project, Quotation Number, Date, Valid Until, Line Items, Total Amount, Notes.

Supported states: Draft, Sent, Accepted, Rejected, Expired.

### 3.6.5 Invoices (FR-ERP-008)

Providers manage invoices for their customers. Invoice information: Invoice Number, Client, Related Project, Issue Date, Due Date, Amount, Status.

Supported states: Draft, Issued, Partially Paid, Paid, Cancelled.

### 3.6.6 Expenses (FR-ERP-009)

Providers record business expenses. Expense information: Category, Amount, Date, Supplier, Notes, Attachment.

### 3.6.7 Calendar (FR-ERP-010)

Lightweight calendar for meetings, milestones, project deadlines, and reminders.

### 3.6.8 Reports (FR-ERP-011)

Operational reports: Project Summary, Sales Report, Invoice Report, Expense Report, Lead Conversion Report, Activity Report.

Notifications: New Lead, Project Created, Milestone Due, Invoice Due, Expense Added, Quotation Accepted, Calendar Reminder.

## Key Entities

Database tables named in the source (map these to migrations/models; table names as specified, not necessarily final):

- `erp_projects`
- `erp_project_milestones`
- `erp_project_documents`
- `crm_leads`
- `crm_activities`
- `quotations`
- `quotation_items`
- `invoices`
- `invoice_items`
- `expenses`
- `calendar_events`

## API

Group prefix: `/api/v1/erp/*` (mounted in this repo via `Modules/ERP/routes/api.php`, currently an empty stub group).

Endpoints listed in the source (paths are relative; actual routes are namespaced under the module prefix above):

- `GET /erp/dashboard`
- `GET /erp/projects`, `POST /erp/projects`, `PUT /erp/projects/{id}`
- `GET /erp/leads`, `POST /erp/leads`
- `GET /erp/quotations`, `POST /erp/quotations`
- `GET /erp/invoices`, `POST /erp/invoices`
- `GET /erp/expenses`, `POST /erp/expenses`
- `GET /erp/calendar`

The source does not give full CRUD detail for every entity (e.g. no explicit `DELETE`/`PATCH` routes, no milestone/document/expense-by-id endpoints) — treat the list above as illustrative, not exhaustive, and fill gaps per the project's `.claude/skills/laravel-endpoint` conventions when implementing.

## Workflow (Section 5.9)

Actor: ERP User.

1. Activate ERP.
2. Create clients and leads.
3. Create internal projects.
4. Manage milestones.
5. Create quotations.
6. Generate invoices.
7. Record expenses.
8. Monitor reports.

## Edge Cases (from source)

- Marketplace project imported twice.
- Deleted customer linked to active project.
- Invoice created for archived project.
- Milestone exceeds project budget.
- Expense exceeds configured limits.
- Subscription expires while ERP is in use.

## Gaps / Not Specified in Source

- No detail on ERP-side permission/role model beyond "Service Provider" as primary actor.
- No specification of how (or whether) a won Umrany bid is surfaced to the provider as a suggested ERP project to import — only that "Marketplace projects may be manually added."
- No currency/multi-tenancy detail specific to ERP entities (platform-wide multi-currency support is mentioned only at the platform level in `docs/business/overview.md`).
- Full CRUD/route surface beyond the endpoints listed above is unspecified.
