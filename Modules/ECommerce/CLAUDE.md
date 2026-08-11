# ECommerce Module

Multi-vendor marketplace for physical construction materials: suppliers run stores and manage products/inventory/pricing; customers buy across multiple suppliers in one checkout; the platform collects payment, deducts fees/commission, and settles supplier wallets after OTP-confirmed delivery.

Full reference (business objective, FR-by-FR detail, edge cases, notification list, proposed table names): `docs/modules/ecommerce.md`.

**Implementation status:** scaffolding only right now — no controllers, migrations, or routes exist yet beyond an empty `routes/api.php` group. This file describes the target domain, not existing code.

## Entities

- **Supplier Store** — a supplier's storefront config (name, logo, cover, description, address, policies); gated by E-Commerce subscription access, config persists even when access is disabled.
- **Product** — physical construction item owned by a supplier (no digital products/services); has status (Draft/Active/Out of Stock/Inactive/Archived) and belongs to a Category.
- **Category / Subcategory** — admin-owned hierarchy; bilingual (AR/EN) names; deactivating a category doesn't detach historical products from it.
- **Product Variant** — a purchasable configuration of a product (size, color, thickness, etc.) with its own price, SKU, and stock, independent of the base product.
- **Cart / Cart Item** — customer's shared cart; can hold items from multiple suppliers at once; each item tracks its supplier; price is re-checked at checkout, not frozen at add-to-cart.
- **Checkout** — the single-payment transaction a customer completes for a cart that may span multiple suppliers.
- **Order (Supplier Order) / Order Item / Order Status History** — one order per supplier per checkout, created after payment succeeds; fulfillment status is supplier-driven, financial status is system-driven; every transition is logged.
- **Delivery OTP** — one-time code per supplier order, visible only to the customer; supplier enters it to confirm delivery.
- **Supplier Wallet / Wallet Transaction** — per-supplier ledger with Pending / Available / Withdrawn / Refunded balances; net amount per order = gross − gateway fees − platform commission ± refund adjustments, computed and stored permanently at order time.
- **Withdrawal Request** — supplier's request to cash out Available balance; goes through Requested → Under Review → Approved → Processing → Paid (or Rejected/Cancelled), admin-mediated.
- **Customer Wallet / Wallet Transaction** — customer-side ledger, primarily for eligible refund/cancellation credits; immutable transaction log.
- **Supplier Review / Product Review** — customer feedback, only unlockable after an order reaches Completed (post OTP-confirmed delivery).

## Key workflow: purchase to settlement

- Customer adds products from one or more suppliers into one shared cart (cart tracks per-supplier subtotals).
- Customer checks out once: stock and price are re-validated immediately before payment; **one payment** is taken for the whole cart.
- On payment success, the platform automatically splits the checkout into **one independent order per supplier** — a single payment fans out into multiple supplier orders, never a single order with multiple sellers attached.
- Per supplier order, the platform computes net supplier amount = gross order amount − payment gateway fees − platform commission ± refund adjustments; rates are admin-configurable and snapshotted per order (later rate changes never touch historical orders).
- Supplier fulfills and ships independently (platform provides no logistics); at delivery, the supplier must obtain the order's unique OTP from the customer and enter it correctly to confirm delivery.
- OTP confirmation is what marks the order Completed and flips settlement eligibility — **this is the gate before supplier wallet funds move from Pending toward Available**.
- Supplier withdraws only from Available balance, via an admin-approved withdrawal request; Pending balance is never withdrawable.
- Customer cancellation is only guaranteed before the supplier order enters Preparing status; cancelled amounts go to the customer wallet per policy. A refund requested after commission has already been calculated/settled is a known unresolved edge case in the source requirements — don't assume a clawback mechanism, confirm the design before implementing.
- Reviews (supplier + product) unlock only after Completed.

## API prefix

`/api/v1/ecommerce` — mounted by `Modules/ECommerce/app/Providers/RouteServiceProvider.php` wrapping `routes/api.php` (`Route::prefix('v1/ecommerce')`). Currently an empty route group, already wrapped in `['auth:sanctum', 'module.entitlement:ecommerce']` — a customer who hasn't subscribed to ECommerce gets a 402 before any controller runs. See `docs/architecture/module-boundaries.md` § Independent purchasability; don't duplicate that check inside individual endpoints.

## Dependencies

Per `docs/architecture/module-boundaries.md`, ECommerce may depend on `Modules\Core` only, and only through its public contracts/events — never another business module's Eloquent models or internals:

- **Auth / user identity** — via `Modules\Core\Contracts\...` (or the authenticated user/guard Core provides).
- **Provider/supplier verification status** — a supplier's E-Commerce access should check Core's provider-profile/verification contract, not duplicate verification logic here.
- **Subscriptions** — marketplace access (store activation) is subscription-gated per the requirements; check Core's subscription contract rather than reimplementing plan/entitlement logic.
- **Wallet / finance primitives** — Core owns platform-wide wallet/finance primitives; how ECommerce's supplier/customer wallets (Pending/Available/Withdrawn/Refunded) relate to those primitives is an open design question (see gap noted in `docs/modules/ecommerce.md`) — don't assume ECommerce owns a fully separate ledger without checking Core's contracts first.
- **Notifications** — fire through `Modules\Core\Events\...` domain events (e.g., order/payment/withdrawal/review notifications listed in the reference doc) rather than calling a notification service directly.

Never `use Modules\Projects\...`, `use Modules\ERP\...`, or `use Modules\AI\...` from here, and never reach into `Modules\Core`'s Eloquent models directly — Core only, via `Contracts`/`Events`.

## Gotchas

- **Money fields must be `decimal`, never `float`.** Gross amount, gateway fees, commission, net supplier amount, refund adjustments, wallet balances — all decimal columns/casts. Float arithmetic on money will produce silent rounding errors in commission and settlement math.
- **OTP-based delivery confirmation gates settlement.** Do not move a supplier's wallet balance from Pending to Available (or make it eligible for withdrawal) before the order's delivery OTP has been verified and the order is Completed. Settlement eligibility is explicitly tied to OTP confirmation, not to shipment or any earlier status.
- **One checkout → many orders, not one order with many sellers.** A single customer payment always fans out into independent per-supplier orders (one order per supplier per checkout). Never model checkout as a single `orders` row referencing multiple suppliers/line-item-owners — each supplier order is its own row with its own status lifecycle, its own OTP, and its own settlement calculation.
- Price and stock are authoritative only at checkout time, not at add-to-cart time — always re-validate both immediately before taking payment.
- Historical order data must never be mutated by later product edits (price changes, status changes) — an order retains the price/details as of purchase time.
- Commission/fee rate changes must be forward-only — never retroactively recompute historical supplier settlements when admin-configured rates change.
