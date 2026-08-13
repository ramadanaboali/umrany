# Module Boundaries

## The five modules

| Module | Owns | API prefix |
|---|---|---|
| `Modules/Core` | Auth, user profiles, provider identity, subscriptions, verification, chat, notifications, wallet/finance primitives, base reporting engine, admin/RBAC | `/api/v1/core` |
| `Modules/Projects` | Project bidding platform: publish project → receive quotations → chat → award → completion, tenders | `/api/v1/projects` |
| `Modules/ECommerce` | Multi-vendor marketplace for construction materials: stores, products, cart, checkout, orders, delivery, supplier/customer wallets, reviews | `/api/v1/ecommerce` |
| `Modules/ERP` | Lightweight CRM, quotations, invoices, expenses for service providers — deliberately independent of `Modules/Projects` | `/api/v1/erp` |
| `Modules/AI` | BOQ analysis, provider matching, offer comparison — calls an external AI/LLM provider, exposes service contracts other modules call | `/api/v1/ai` |

Each module is a self-contained `nwidart/laravel-modules` package: its own `app/`, `routes/api.php`, `database/migrations`, `config`, `tests`, and `module.json`. Routes are mounted by the module's own `RouteServiceProvider`, which wraps `routes/api.php` in `Route::middleware('api')->prefix('api')->group(...)`; the module-alias/version prefix (`v1/ecommerce`, `v1/erp`, ...) is declared inside that routes file itself. There is no web/session route registration in any module — see `system-architecture.md`.

## The hard rule

**A module may depend on `Modules/Core` contracts and events only.** The four business modules — Projects, ECommerce, ERP, AI — never reference each other's Eloquent models, migrations, or internal classes directly. The only two legal channels for one business module to react to or use another business module's behavior are:

1. **Domain events** published under `Modules/Core/app/Events` — a business module fires a Core event (e.g. `OrderPlaced`, `ProjectAwarded`) and any other module can register a listener for it without knowing who fired it or why.
2. **An explicit `Contracts` interface** owned and defined by the module that provides the behavior, bound to its concrete implementation in that module's own service provider. A consuming module type-hints the interface and lets the container resolve it — it never instantiates or imports the concrete class.

Core is the sole exception: every module is allowed to depend on Core, because Core is where the platform-wide primitives live (identity, subscriptions, wallet, notifications, RBAC). Core itself depends on nothing — no `use Modules\Projects\...`, `use Modules\ECommerce\...`, etc. This one-way dependency (Core ← everything, nothing ← Core) is what keeps the module graph a tree instead of a mesh; it's also what would let Core be split out as its own service first, if the platform ever needed to move off the monolith.

This is enforced by convention and code review, not by a build step — there is no PHP-CS-Fixer rule or Composer autoload restriction stopping a stray `use Modules\ECommerce\Models\Order;` from compiling inside `Modules/ERP`. The `.claude/skills/module-boundary-check` skill exists specifically to catch this at review time; run it on any PR that touches more than one module.

## Bad vs. good example

Say `Modules/ERP` needs to know the total value of a customer's ECommerce orders when generating a CRM report.

**Bad — reaches directly into ECommerce's model layer:**

```php
namespace Modules\ERP\app\Services;

use Modules\ECommerce\Models\Order; // boundary violation: ERP importing ECommerce internals

class CustomerValueReport
{
    public function totalOrderValue(int $customerId): float
    {
        return Order::where('customer_id', $customerId)->sum('total');
    }
}
```

This compiles, and it will work today — which is exactly the problem. It couples ERP's report to ECommerce's schema (a migration renaming `total` to `total_amount` silently breaks ERP), and it means ECommerce can never be extracted or changed without grepping every other module.

**Good, option A — consume an explicit contract owned by ECommerce:**

```php
namespace Modules\ERP\app\Services;

use Modules\ECommerce\Contracts\CustomerOrderSummary; // interface, not a model

class CustomerValueReport
{
    public function __construct(
        private readonly CustomerOrderSummary $orderSummary,
    ) {}

    public function totalOrderValue(int $customerId): float
    {
        return $this->orderSummary->totalValueFor($customerId);
    }
}
```

`Modules/ECommerce` defines `Contracts\CustomerOrderSummary` and binds it to a concrete `OrderSummaryService` in its own `EcommerceServiceProvider`. ERP only ever sees the interface. ECommerce's internal schema can change freely as long as the contract's behavior doesn't.

**Good, option B — react to a Core domain event instead of pulling data on demand:**

```php
namespace Modules\ERP\app\Listeners;

use Modules\Core\Events\OrderCompleted;

class RecordOrderInCustomerLedger
{
    public function handle(OrderCompleted $event): void
    {
        // $event exposes plain data (customerId, amount, occurredAt, ...),
        // not an ECommerce Eloquent model.
    }
}
```

ECommerce fires `Modules\Core\Events\OrderCompleted` (a Core event, so any module can listen without depending on ECommerce) when an order is finalized; ERP listens and updates its own ledger. Neither module knows the other exists.

## Independent purchasability: modules stay usable if a customer skips one

Per the business model, a provider can subscribe to ERP without ECommerce, or ECommerce without the Projects platform, etc. Two implementation approaches were considered:

- **Disable the nwidart module entirely** (`php artisan module:disable ECommerce`) for accounts that haven't bought it. Rejected: nwidart's enable/disable is **application-wide**, not per-user — disabling ECommerce would remove it for every customer, not just the ones who didn't buy it.
- **Gate access per-request based on the authenticated user's entitlement**, while every module stays enabled at the application level for everyone. This is what's implemented.

Every business module's route group (`Modules/Projects|ECommerce|ERP|AI/routes/api.php`) is wrapped in:

```php
Route::prefix('v1/ecommerce')
    ->middleware(['auth:sanctum', 'module.entitlement:ecommerce'])
    ->group(function () { /* ... */ });
```

`module.entitlement:<alias>` resolves to `Modules\Core\Http\Middleware\EnsureModuleEntitlement`, which asks a single Core-owned contract — `Modules\Core\Contracts\ModuleEntitlementChecker::hasAccess($userId, $moduleAlias)` — whether the current user may use that module, and returns `402 Payment Required` if not. The contract is bound in `CoreServiceProvider` to `Modules\Core\Services\SubscriptionEntitlementChecker`.

This is the same one-way-dependency shape as everything else in this doc: a business module's routes depend on a Core contract, never on another business module, and the actual subscription logic lives entirely inside that one Core class. Today `SubscriptionEntitlementChecker` always returns `true` — this app is intentionally a bare modular skeleton (see root `CLAUDE.md`) with no Subscription table yet. When that table exists (`docs/modules/core.md` § Subscription), only `SubscriptionEntitlementChecker` needs to change; no business module's routes or controllers do.

`Modules/Core`'s own routes are **not** gated this way — Core is the mandatory substrate every account has by definition (auth, health checks, etc.), never something to "not buy."

## User capability resolution — a second, sibling contract to `ModuleEntitlementChecker`

`Modules\Core\Contracts\ModuleEntitlementChecker` (above) answers a security-critical, per-module
boolean: may this request proceed. `Modules\Core\Contracts\UserCapabilityResolver` answers a
different question — a full, UI-hint read model of which capabilities an account currently has
(`Modules\Core\Data\UserCapabilities`: `isProvider`, `providerVerified`, `hasEcommerceAccess`,
`hasErpAccess`, `maxProjectOffers`), exposed at `GET /api/v1/core/me/capabilities`.

This exists because of a specific, explicit product rule (`docs/business/personas.md`): "Project
Owner", "Supplier", "ERP User", and "Customer" are never stored roles on `User` — they're computed
every time from `Provider` existence/verification + subscription state. `App\Models\User`
deliberately does not use Spatie's `HasRoles` trait for this reason; that trait is reserved for
`Modules\Core\Models\Admin` (the `admin` guard), a completely separate, genuinely role-based
identity. Don't add a `role` column to `users` and don't give `User` `HasRoles` — if you need a
new derived capability, add a field to `UserCapabilities` and compute it in
`Modules\Core\Services\CapabilityResolver`, the same way `isProvider`/`providerVerified` are
computed there today.

**The client-trust boundary**: `UserCapabilityResolver`'s output is advisory only. No endpoint may
treat a client-supplied copy of this DTO as authoritative — every protected action independently
re-derives entitlement from `ModuleEntitlementChecker`/the `Provider`/subscription tables at
request time, the same "hiding a UI control is never sufficient authorization" rule the source
spec states for admins, applied here to end users too.

`Modules\Core\Models\Provider.has_ecommerce_access`/`has_erp_access` are a denormalized read-cache
of entitlement (so an admin list/report query doesn't need to join subscription tables), kept in
sync by domain events once Subscription exists (`docs/business/roadmap.md` Phase 2) — currently
hardcoded to `false` at Provider creation since no subscription can grant them yet. Never treat
these two columns as authoritative for a security decision; they're a projection, not the record.

## Why Core is the one shared dependency

Core holds the things that are true regardless of which business module a request is touching: who the user is, what they're subscribed to, what their wallet balance is, what role/permissions they have, how they're notified, how they chat. Every one of the four business modules needs all of that on nearly every request — duplicating it per module would mean four copies of auth, four wallets, four notification systems to keep consistent. Centralizing it in Core and letting the business modules depend one-way on it gives every module the same identity/billing/notification substrate without any of them depending on each other.
