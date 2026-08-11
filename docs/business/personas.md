# Umrany Platform Actors (Personas)

Source: Umrany System Analysis & Software Requirements Specification, Chapter 4 ("Platform Actors").

## Design Principle: One Account, Multiple Roles

Umrany does not rely on a rigid end-user role system with separate account types. Instead, a user's effective access is calculated dynamically from:

- Account status.
- Verification status.
- Provider status.
- Active subscription features.
- Standalone services (e.g., a standalone ERP or E-Commerce subscription).
- Resource ownership.
- Business rules.

This is a **deliberate platform design choice, not an edge case**: the same person may simultaneously publish a construction project (as a Project Owner), submit an offer to someone else's project (as a Service Provider), purchase products (as an E-Commerce Customer), sell products (as a Supplier), and use the ERP — all under one account, with no duplicate accounts required. The source material states this explicitly supports "the real-world nature of the construction industry where companies frequently operate as buyers and service providers simultaneously." Provider classification (contractor, supplier, consultant, etc.) likewise does not require separate system accounts, and a Project Owner or Service Provider "account type" is not a separate account — it is a role a Registered User account can activate.

The main actors defined in the platform are: Guest, Registered User, Project Owner, Service Provider, E-Commerce Customer, Supplier, ERP User, Admin User, Super Admin, and External System.

## Guest

Any visitor accessing Umrany without authentication.

Guests may:

- Browse public website pages.
- Browse public provider profiles.
- Browse public E-Commerce products.
- Browse available public categories.
- Access SEO landing pages.
- View general subscription information.
- Register a new account.
- Log in to an existing account.

Guests cannot:

- Create projects.
- Submit project offers.
- Start project conversations.
- Purchase products.
- Access wallets.
- Subscribe to paid services.
- Use ERP.
- Use protected AI functionality.
- Submit reviews.

## Registered User

An authenticated individual with a valid Umrany account. The registered account is the foundation for all additional platform capabilities — every other end-user persona below (Project Owner, Service Provider, Customer, Supplier, ERP User) is a capability layered on top of a Registered User account, not a separate account type.

Registered Users may:

- Manage personal profiles.
- Manage notification preferences.
- Create projects.
- Purchase E-Commerce products.
- View personal orders.
- Manage customer wallet balance.
- Activate a Service Provider profile.
- Subscribe to eligible platform services.
- View platform notifications.

Some features additionally require: email/mobile verification, provider activation, provider verification, an active subscription, E-Commerce activation, or ERP activation.

## Project Owner

Any registered user who creates construction-related projects through the Project-Based Platform. A separate Project Owner account type is not required.

Project Owners may:

- Create, save as draft, publish, and edit eligible projects.
- Upload BOQ files and project attachments.
- Define project categories, location, budget, expected duration, and offer deadlines.
- View submitted offers and compare them, including using AI Offer Comparison.
- View provider profiles.
- Start conversations with providers who submitted offers, and request offer modifications through chat.
- Accept provider offers, including accepting multiple providers on one project.
- Keep a project open after partial acceptance, or close it.
- Access accepted providers' contact information (only after acceptance).
- Participate in tenders and auction-related workflows where applicable.

**Privacy rule:** a Project Owner's private information (personal name, mobile number, email address, WhatsApp number, exact address) remains hidden from providers before offer acceptance. Once an offer is accepted, permitted contact information becomes available to the accepted provider only.

## Service Provider

A registered user who activates a professional provider profile. Provider classification does not require a separate system account, and one provider profile may represent one or multiple business activities, such as:

- Contractor
- Supplier
- Consultant
- Engineering Office
- Manufacturer
- Interior Design Company
- Maintenance Company
- Specialized Construction Company

Depending on subscription and verification status, Service Providers may:

- Manage public company profile and select multiple business categories.
- Upload company documents; complete government verification and/or manual verification.
- Manage portfolio items.
- View and filter projects; receive AI project recommendations.
- Submit, modify, or withdraw project offers.
- Participate in conversations initiated by project owners.
- Receive accepted project owners' contact information (after acceptance).
- Purchase subscription plans.
- Activate ERP independently.
- Activate E-Commerce independently where applicable.

**Offer limits:** the number of offers a provider may submit is controlled by the provider's active subscription configuration, read dynamically from subscription features — the system is explicitly required not to depend on fixed plan names.

## E-Commerce Customer

Any registered user may become an E-Commerce Customer simply by purchasing physical construction products through the platform — there is no separate activation step described for this role.

E-Commerce Customers may:

- Browse, search, and filter products; select product variants.
- Add products from multiple suppliers to one cart and complete checkout with a single payment.
- Pay online.
- View supplier-specific orders and track order statuses.
- Request eligible cancellations.
- Access the delivery OTP.
- View customer wallet.
- Receive eligible refund credits.
- Review purchased products and review suppliers after completed purchases.

## Supplier

A Service Provider with active E-Commerce access. That access may come from a subscription plan that includes E-Commerce access, or a standalone E-Commerce subscription.

Suppliers may:

- Manage supplier store; create, edit, and archive products.
- Manage product variants, prices, and stock.
- Receive supplier-specific orders and update fulfillment statuses.
- Coordinate shipping independently.
- Confirm delivery using the customer's OTP.
- View supplier wallet and settlement information; request wallet withdrawals.
- View sales reports.
- Receive product reviews and supplier reviews.

**Shipping responsibility:** the Supplier remains responsible for fulfillment and delivery. Umrany does not act as a logistics provider.

## ERP User

A Service Provider with active ERP access, originating from either the main subscription plan or a standalone ERP subscription.

ERP Users may:

- Access the ERP dashboard.
- Manage CRM leads and pipeline; record follow-ups, calls, and meetings.
- Manage clients.
- Create internal projects; manage project milestones; upload project documents.
- Create quotations; manage invoices; record expenses.
- Use the calendar.
- View ERP reports.

**Business rule:** ERP projects are independent from Project-Based Platform projects. Projects originating from Umrany may be added manually to the ERP, and purely external projects may also be created directly in the ERP.

## Admin User

Admin Users operate and manage the Umrany platform. Administrative capabilities are controlled entirely by assigned permissions — an administrator does not receive access based solely on a fixed role name (see the RBAC model below).

Depending on assigned permissions, an administrator may manage: users, providers, verification, projects, offers, E-Commerce, orders, payments, wallets, withdrawals, subscriptions, CRM, reports, SEO, CMS, notifications, support, countries, currencies, categories, settings, integrations, and audit logs.

## Super Admin

The highest administrative authority within Umrany, with unrestricted access to all administrative modules, system settings, permissions, roles, reports, and platform operations.

Business rules specific to this actor:

- Super Admin access bypasses standard role restrictions.
- Super Admin actions remain auditable; sensitive operations are still recorded in audit logs even for this actor.
- Super Admin accounts receive enhanced security controls.

## External System

Represents third-party services integrated with Umrany, not a human actor. Main external systems named in the source material:

- Saudi Government Commercial Registration Service.
- Payment Gateway.
- Email Delivery Service.
- Push Notification Service.
- Analytics Services (Google Analytics, Google Search Console, Meta Pixel where enabled).

Access rules: external systems may only access explicitly authorized platform endpoints or integration services, and integration credentials must never be exposed to end users.

## Note on Admin Roles and Permissions

Admin User and Super Admin capabilities are governed by a dynamic Role-Based Access Control (RBAC) model rather than fixed roles: Admin User -> Role -> Permissions, where roles act as permission containers rather than fixed application identities, and administrators with sufficient authority can create new roles at any time without a software change. Example roles named in the source (Finance, Sales, Customer Support, Operations, Marketing, Verification Team, Content Manager) are explicitly described as examples only, not to be hard-coded. Permissions follow a `module.action` naming convention (e.g., `projects.create`, `wallets.adjust`, `withdrawals.approve`), and hiding a UI control is explicitly never sufficient authorization on its own — server-side permission validation is mandatory for every protected action.
