# Umrany Core Products

Source: Umrany System Analysis & Software Requirements Specification, Chapter 1 ("Core Products" / "Project Scope") and Chapter 2 ("Platform Architecture" / "System Components").

Umrany consists of four primary products that operate independently while remaining fully integrated within the same platform, supported by a centralized Administration Portal and a layer of Shared Platform Services.

## Project-Based Platform

**What it does:**

A project bidding platform where project owners publish construction requirements and receive quotations from qualified service providers. It represents the procurement layer of Umrany.

- Project owners publish projects with optional advanced information: budget, BOQ files, attachments, expected duration, and additional specifications.
- Service providers browse projects (according to their subscription plan) and submit quotations containing pricing, estimated duration, descriptions, and supporting documents.
- Communication between project owner and provider happens through an integrated chat system before project acceptance.
- The project owner's contact information remains hidden until a quotation is accepted.
- A project may receive multiple quotations and may accept multiple service providers, depending on requirements. The project owner controls whether the project stays open for additional providers or is closed after selecting participants.

**What it explicitly does NOT do:**

- It does **not** participate in project execution.
- It does **not** take on contractual obligations between the parties.
- It does **not** perform financial settlement between project owner and provider.
- It does **not** perform dispute resolution between the involved parties.

The platform's role is limited to connecting project owners with service providers and managing the digital bidding/communication process; everything that happens after a provider is selected (the actual construction work, contracts, payment between the two parties, disputes) happens outside the platform.

## Construction E-Commerce

**What it does:**

A specialized multi-vendor marketplace dedicated exclusively to construction materials.

- Verified suppliers create stores and manage products, variants, pricing, inventory, product images, and customer orders through a unified dashboard.
- Customers can purchase products from multiple suppliers in a single checkout; the system automatically splits the purchase into supplier-specific orders internally while processing one customer payment.
- Umrany manages payments, settlements, and platform operations: it collects payment, deducts payment gateway fees and platform commissions, and transfers the remaining balance to each supplier's wallet per configurable settlement policy (see `docs/business/business-model.md`).
- Product delivery is confirmed through a One-Time Password (OTP) provided by the customer, gating settlement on confirmed order completion.
- The platform provides order management, supplier wallets, customer reviews, settlement management, refund workflows, and financial reporting.

**What it explicitly does NOT do:**

- Suppliers, not Umrany, remain fully responsible for shipping, delivery, and logistics.
- Umrany does **not** act as a logistics provider.
- The operational model is described as deliberately simple in order to minimize platform liability — i.e., the platform's financial exposure and operational involvement is limited to payment collection, commission deduction, and wallet settlement, not physical fulfillment.

## ERP Platform

**What it does:**

An independent SaaS product for construction service providers, offering lightweight business management tools.

- Includes project management, customer relationship management (CRM), quotations, invoices, expense management, and operational reporting through a simplified user experience.
- Service providers may manually create ERP projects originating from Umrany platform activity, or create projects for external clients entirely outside Umrany — the ERP functions as a standalone business management solution.
- Deliberately avoids "unnecessary enterprise complexity," focusing only on essential operations needed by contractors, suppliers, engineering offices, consultants, and similar organizations.

**What it explicitly does NOT do:**

- Projects managed inside the ERP are **completely independent** from Project-Based Platform projects — the ERP is not a downstream execution layer of the bidding platform; the two use separate project records even when an ERP project happens to originate from a project won on the Project-Based Platform.
- The ERP is explicitly described as "unlike traditional enterprise resource planning systems" — it is not meant to grow into a full-scale ERP with the complexity that implies.

## Artificial Intelligence Services

**What it does:**

AI is integrated as a cross-platform service rather than an independent application, currently comprising three services:

1. **BOQ Analysis** — extracts construction items from uploaded Bill of Quantities documents and organizes them into structured digital information.
2. **Intelligent Provider Matching** — recommends suitable service providers for newly published projects, and simultaneously recommends relevant projects to qualified providers.
3. **AI Offer Comparison** — analyzes multiple quotations and presents structured comparisons to simplify evaluation and decision-making for project owners.

These services aim to improve operational efficiency, reduce manual effort, and enhance decision quality.

**What it explicitly does NOT do:**

- AI services are explicitly stated to enhance decisions "without replacing human judgment" — they are advisory/automation aids layered across the other products, not an independent product a user subscribes to on its own within the current scope, and not a decision-making authority in themselves.

## Administration Portal

**What it does:**

The centralized control center for the entire Umrany ecosystem. Every configurable component of the platform is managed here, including:

- Users, providers, verification requests.
- Subscriptions, financial operations, settlements.
- Reports, platform settings, AI configuration.
- SEO pages, notifications, countries, currencies, categories.
- Content management and system monitoring.

It implements a dynamic Role-Based Access Control (RBAC) architecture: every function is protected by granular permissions, administrators can create custom roles and assign specific permissions, and access control does not require software modification. Comprehensive dashboards provide operational, financial, analytical, and business reports.

**What it explicitly does NOT do / boundaries:**

- Access is not granted by fixed role name alone — an administrator's actual capabilities are always determined by assigned permissions, not by holding a role labeled a certain way.
- Hiding a UI element (e.g., a button) is explicitly stated to never be sufficient authorization on its own — server-side permission validation is mandatory for every protected action.

## Shared Platform Services

**What it does:**

Common infrastructure used across the entire ecosystem so users experience a unified platform regardless of which business product they are currently using:

- Authentication and authorization.
- Subscription management.
- Payment processing and wallet management.
- Notification delivery and internal chat.
- Audit logging and reporting.
- File management and search.
- Verification (including government integration).
- API integration.
- Multilingual and multi-currency support.
- SEO management, analytics, and centralized configuration.

**Boundary:**

These are described purely as shared infrastructure layers consumed by the four core products and the Administration Portal — they are not a standalone product with their own end-user-facing business purpose; their scope is defined by what the products above need from them.
