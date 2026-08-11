# ECommerce Module Reference

Source: platform requirements specification, ECOM section (Module IDs `ECOM`, `ECOM-STORE`, `ECOM-PRODUCT`, `ECOM-CATEGORY`, `ECOM-VARIANT`, `ECOM-CART`, `ECOM-CHECKOUT`, `ECOM-ORDER`, `ECOM-DELIVERY`, `ECOM-WALLET`, `ECOM-WITHDRAWAL`, `ECOM-CUSTOMER-WALLET`, `ECOM-REVIEWS`). This document summarizes the functional requirements (FR-ECOM-001 through FR-ECOM-023) grouped by sub-area, not a line-by-line transcription.

**Implementation status:** as of this writing, `Modules/ECommerce` in this repository is scaffolding only — an empty `routes/api.php` route group, no controllers, no migrations, no models. Everything below describes the *requirement*, not existing code. Treat entity/table names as the source's proposed names, not confirmed schema.

## Module Overview (ECOM)

| Property | Value |
|---|---|
| Priority | Critical |
| Complexity | Very High |
| Primary Actors | Customer, Supplier |
| Dependencies (per source) | Authentication, Provider Profile, Payment Gateway, Wallet, Notifications |
| Related Modules | Reviews, Subscriptions, Reports, Admin Portal |
| API Group (per source) | `/api/v1/ecommerce/*` |

**Business objective:** a specialized multi-vendor marketplace for physical construction products and materials. Suppliers publish and manage products; customers browse, compare, purchase, and review through a centralized purchasing experience. The module supports multiple suppliers within a single customer checkout while separating supplier-specific orders internally. UMRANY (the platform) handles payment collection, commission deduction, wallet settlement, and order tracking; suppliers remain responsible for fulfillment, shipping, and delivery.

---

## Store (ECOM-STORE)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / High |
| Actors | Supplier |
| Dependencies | Provider Profile, Subscription Management |
| Related | Products, Orders, Wallet |

**Business objective:** let eligible suppliers operate a digital store while keeping the same provider identity used across the rest of the platform.

**Key entity:** Supplier Store — store name, logo, cover image, description, supplier information, business address, contact information, store policies, delivery notes.

**Functional requirements (FR-ECOM-001, FR-ECOM-002):**
- Store activation is gated by E-Commerce access, which a supplier gets either bundled inside an active subscription plan or via a standalone E-Commerce subscription. A supplier must already have an active provider profile. When the underlying subscription expires, store access is disabled, but the store's configuration is retained (not deleted) so it can resume when access is restored.
- Suppliers manage their own store information (name, logo, cover, description, address, contact, policies, delivery notes). Public-facing fields may be inherited from the provider profile rather than entered twice. Some store changes may require administrative approval, per platform settings — the source does not enumerate which fields require approval.

---

## Product (ECOM-PRODUCT)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / High |
| Actors | Supplier |
| Dependencies | Categories, Supplier Store |
| Related | Variants, Cart, Orders, Reviews |

**Business objective:** let suppliers create and manage physical construction products with full pricing, categorization, media, availability, and variant information.

**Key entity:** Product — name, description, main category, subcategory, images, base price, SKU, stock status, available quantity, unit of measurement, specifications, variant configuration, supplier reference.

**Functional requirements (FR-ECOM-003 through FR-ECOM-005):**
- **Create Product:** physical construction products only — digital products and services are explicitly out of scope. A product must belong to a valid E-Commerce category, and the supplier must hold active E-Commerce access. Visibility is controlled by product status; purchasability depends on configured stock.
- **Edit Product:** edits never rewrite historical order data. Price changes apply only to future purchases — an order already paid for keeps the price the customer actually paid, even after the supplier changes the listed price.
- **Product Status Management:** five states — Draft, Active, Out of Stock, Inactive, Archived. Draft products are visible only to the owning supplier. Inactive and Archived products cannot be purchased. Out-of-Stock products may stay publicly visible but cannot be added to cart unless the platform explicitly supports backordering.

---

## Category (ECOM-CATEGORY)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / Medium |
| Actors | Administrator |
| Dependencies | Administration Portal |
| Related | Products, Search |

*(Source has no separate Business Objective paragraph for this sub-area — it goes straight from the module info table to the functional requirement.)*

**Key entity:** Category hierarchy — Main Category, Subcategory, with room for "additional hierarchy levels where required" (depth beyond two levels is not otherwise specified).

**Functional requirement (FR-ECOM-006 — Manage Product Categories):** administrators own the entire category tree. Category names must support both Arabic and English. Categories can be activated/deactivated; when deactivated, existing products stay associated with that category for historical purposes (they aren't reassigned or orphaned). Category display ordering is configurable.

---

## Variant (ECOM-VARIANT)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / High |
| Actors | Supplier |
| Dependencies | Products |

*(No Related Modules or Business Objective-adjacent detail beyond the paragraph below in the source.)*

**Business objective:** support construction products that come in many selectable configurations (e.g., a cement bag in multiple weights, a pipe in multiple diameters) without forcing the supplier to create a separate product record for every combination.

**Key entity:** Variant attribute/combination. Example attributes given in the source: Size, Color, Thickness, Material, Length, Width, Grade, Capacity, Finish — these are illustrative, not an exhaustive fixed list; attributes are supplier-configurable per product.

**Functional requirement (FR-ECOM-007 — Manage Product Variants):** each variant combination may carry its own price, its own SKU, and its own stock quantity, independent of the parent product's base values. Combinations marked unavailable cannot be purchased.

---

## Cart (ECOM-CART)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / High |
| Actors | Customer |
| Dependencies | Products, Product Variants |
| Related | Checkout |

*(No separate Business Objective paragraph in the source for this sub-area.)*

**Key entities:** Cart, Cart Item — product, selected variant, supplier, quantity, unit price, item total, supplier subtotal, overall total. Note that a cart subtotal is already broken out per supplier at this stage, ahead of checkout.

**Functional requirements (FR-ECOM-008, FR-ECOM-009):**
- **Add Product to Cart:** a single cart may hold products from multiple suppliers simultaneously. The selected variant must be available and in-stock; quantity cannot exceed available stock. Price is not frozen at add-to-cart time — it is recalculated before checkout. Adding to cart does not reserve stock unless the platform explicitly configures a reservation behavior (source flags this as conditional, not guaranteed).
- **Manage Cart:** customers can update quantities, remove items, and review the full cart (with the per-supplier/overall totals above) before proceeding to checkout.

---

## Checkout (ECOM-CHECKOUT)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / Very High |
| Actors | Customer |
| Dependencies | Cart, Payment Gateway, Address Management |
| Related | Orders, Wallet |

**Business objective:** process a multi-supplier purchase through a single customer checkout while maintaining independent supplier fulfillment and financial records underneath.

**Key entity:** Checkout — customer information, delivery address, products, variants, quantities, supplier breakdown, payment amount, applicable fees, final total.

**Functional requirement (FR-ECOM-010 — Checkout Cart):** a customer completes one purchase, potentially spanning multiple suppliers, through a single checkout flow and makes exactly one payment for the entire checkout. The platform then internally separates that checkout by supplier so each supplier only ever sees the order items that belong to them. Both stock availability and prices must be re-validated immediately before payment is taken (not just at add-to-cart time).

---

## Order (ECOM-ORDER)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / Very High |
| Actors | Customer, Supplier |
| Dependencies | Checkout, Payment, Notifications |
| Related | Wallet, Reviews |

**Business objective:** manage supplier-specific order fulfillment all the way from successful payment through verified delivery.

**Key entities:** Supplier Order (one per supplier per checkout), Order Item, Order Status History.

**Functional requirements (FR-ECOM-011 through FR-ECOM-013):**
- **Create Supplier Orders:** after a successful payment, the system creates one independent order per participating supplier. Worked example from the source: a customer buys AED 500 from Supplier A and AED 500 from Supplier B in one AED 1,000 transaction; the system creates Supplier Order A and Supplier Order B, and each supplier manages only their own order.
- **Order Status Management:** states are Pending Payment → Paid → Confirmed → Preparing → Ready for Delivery → Out for Delivery → Delivered Pending OTP → Completed, plus terminal side-states Cancelled and Refunded. Suppliers drive the operational fulfillment statuses (Confirmed through Out for Delivery); the *system* drives financial statuses (Paid, Refunded). A Completed order cannot revert to an earlier fulfillment state. Every status transition is recorded in order history.
- **Customer Cancellation Request:** a customer may cancel an eligible supplier order only before it enters Preparing status. Cancelled amounts are returned to the customer wallet per platform policy. Once the supplier starts preparation, the platform no longer guarantees a normal cancellation path — any resolution past that point happens directly between customer and supplier, outside platform-mediated cancellation.

---

## Delivery (ECOM-DELIVERY)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / High |
| Actors | Customer, Supplier |
| Dependencies | Orders, OTP Service |
| Related | Wallet Settlement |

**Key entity:** Delivery OTP — one independent OTP per supplier order (not one per checkout).

**Functional requirements (FR-ECOM-014, FR-ECOM-015):**
- **Generate Delivery OTP:** the OTP is visible only to the customer. The supplier must obtain and enter the correct OTP from the customer to confirm delivery of that specific supplier order. Repeated failed OTP attempts may be rate-limited for security (exact throttling rule not specified in the source).
- **Confirm Delivery:** once the supplier enters the correct OTP, delivery is verified, the order moves to Completed, settlement eligibility is updated (this is the trigger that unblocks wallet settlement — see the end-to-end flow below), and the customer becomes eligible to review both the supplier and the purchased products.

**Shipping responsibility (FR-ECOM-016, same source section, no separate module ID):** the supplier is entirely responsible for shipping and delivery. UMRANY provides no logistics service itself — the supplier chooses the delivery method and coordinates directly with the customer. The platform's role is limited to order status tracking and delivery (OTP) confirmation. Shipping disputes do not automatically create platform liability.

---

## Wallet (ECOM-WALLET)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / Very High |
| Actors | Supplier |
| Dependencies | Payment, Orders |
| Related | Withdrawals, Reports |

**Business objective:** maintain an accurate financial ledger per supplier, separating pending, available, withdrawn, and refunded balances.

**Key entity:** Supplier Wallet, with four distinct balance types: Pending, Available, Withdrawn, Refunded.

**Functional requirements (FR-ECOM-017, FR-ECOM-018):**
- **Supplier Wallet Balance:** every supplier receiving E-Commerce revenue has a wallet tracking those four balance buckets separately (not a single running total).
- **Calculate Supplier Net Amount:** for every successful order, the system computes: Gross Order Amount − Payment Gateway Fees − UMRANY Commission ± Refund Adjustments = Net Supplier Amount. Commission percentages and gateway fees are both configurable through the Administration Portal. Every calculation is stored permanently for audit purposes, and — critically — changing commission settings going forward never rewrites historical transactions' already-computed amounts.

---

## Withdrawal (ECOM-WITHDRAWAL)

| Property | Value |
|---|---|
| Priority / Complexity | Critical / High |
| Actors | Supplier, Administrator |
| Dependencies | Wallet |
| Related | Admin Finance, Reports |

**Key entity:** Withdrawal Request.

**Functional requirements (FR-ECOM-019, FR-ECOM-020):**
- **Request Withdrawal:** suppliers request withdrawal of their *Available* balance only — Pending balance cannot be withdrawn. Minimum withdrawal amount and the settlement waiting period are both admin-configurable (no fixed numbers given in the source). Requests go through administrative processing rather than auto-paying out.
- **Withdrawal Status:** states are Requested → Under Review → Approved → Processing → Paid, with Rejected and Cancelled as alternate terminal states. Every status change is logged. Administrators with the required permission approve or reject requests. A Paid withdrawal permanently reduces the available wallet balance.

---

## Customer Wallet (ECOM-CUSTOMER-WALLET)

| Property | Value |
|---|---|
| Priority / Complexity | High / High |
| Actors | Customer |
| Dependencies | Payments, Refunds |

**Key entity:** Customer Wallet.

**Functional requirement (FR-ECOM-021):** customers get an internal wallet used primarily to hold eligible refunds and platform credits. Eligible cancelled-order amounts are credited here (see the cancellation rule under Order, above). Wallet balance can be spent according to configured payment rules (the source does not specify whether wallet balance can be mixed with a gateway payment in the same checkout, or must fully cover an order — flagged as a gap). Every wallet transaction keeps an immutable ledger record.

---

## Reviews (ECOM-REVIEWS)

| Property | Value |
|---|---|
| Priority / Complexity | High / Medium |
| Actors | Customer |
| Dependencies | Completed Orders |
| Related | Provider Profile, Products |

**Key entities:** Supplier Review, Product Review.

**Functional requirements (FR-ECOM-022, FR-ECOM-023):**
- **Review Supplier:** only verified purchasers can submit a supplier review. Platform configuration may limit this to one review per supplier order. Reviews carry a rating plus written feedback.
- **Review Product:** only products the customer actually purchased can be reviewed, and only after the order is successfully completed. Product ratings roll up into an aggregate product rating.

---

## End-to-end purchase-to-settlement flow

This traces one checkout from cart to supplier payout, tying together Cart, Checkout, Order, Delivery, and Wallet.

1. **Multi-supplier cart.** A customer adds products (with selected variants) from one or more suppliers into a single shared cart. Each cart line already tracks which supplier it belongs to, and the cart view shows a subtotal per supplier alongside the overall total (ECOM-CART).
2. **Single checkout, single payment.** At checkout, stock availability and prices are re-validated immediately before payment (not trusted from cart state). The customer makes exactly **one payment** covering every supplier's items plus applicable fees (ECOM-CHECKOUT, FR-ECOM-010).
3. **Automatic split into per-supplier orders.** Once payment succeeds, the platform creates one independent **Supplier Order per participating supplier** from that single transaction (the AED 500 + AED 500 = AED 1,000 example under FR-ECOM-011). Each supplier only ever sees and manages their own order — this is a one-payment-to-many-orders split, not a single order shared across sellers.
4. **Platform collects payment; fees and commission are computed per order.** For each supplier order, the system calculates: Gross Order Amount − Payment Gateway Fees − UMRANY Commission ± Refund Adjustments = Net Supplier Amount (FR-ECOM-018). Commission and gateway fee rates are admin-configurable and are snapshotted per transaction for audit — later rate changes never retroactively alter an already-computed historical order.
5. **Independent fulfillment per supplier order.** Each supplier order moves through its own operational status lifecycle (Confirmed → Preparing → Ready for Delivery → Out for Delivery), driven by the supplier, not the platform. Suppliers are fully responsible for shipping method and delivery coordination — the platform provides no logistics (FR-ECOM-016).
6. **OTP-gated delivery confirmation.** Each supplier order has its own delivery OTP, visible only to the customer. The supplier must obtain the OTP from the customer at delivery and enter it correctly to confirm delivery for that order (FR-ECOM-014).
7. **Settlement finalizes only after delivery is confirmed.** Entering the correct OTP marks delivery verified, moves the order to Completed, and **updates settlement eligibility** — this is the explicit gate that unlocks moving the previously-computed Net Supplier Amount from Pending Balance toward Available Balance in the supplier's wallet (FR-ECOM-015, FR-ECOM-017). The source does not fully spell out the mechanics of the Pending→Available transition beyond "settlement eligibility is updated" plus a configurable settlement waiting period referenced under Withdrawals — treat the exact maturation trigger/timing as a design decision, not a fixed rule from the source.
8. **Supplier withdrawal.** Only Available Balance (never Pending) can be withdrawn. A withdrawal request goes through an admin-mediated workflow (Requested → Under Review → Approved → Processing → Paid) before funds leave the platform; a minimum withdrawal amount and a settlement waiting period are both admin-configurable (FR-ECOM-019, FR-ECOM-020).
9. **Refund / cancellation workflow.** A customer can cancel an eligible supplier order only before it enters Preparing status; the cancelled amount is returned to the **customer wallet** per platform policy (FR-ECOM-013, FR-ECOM-021), not necessarily back to the original payment instrument — the source doesn't state whether gateway refunds to the original payment method are also supported. The source separately flags, as an open edge case, "refund occurs after commission calculation" — i.e., a refund requested after the supplier's net amount has already been computed and possibly settled — without specifying how the commission/fee portion is clawed back in that case. This is a genuine gap to resolve during design, not something to guess at.
10. **Reviews unlock.** Only after an order reaches Completed (i.e., after OTP-confirmed delivery) does the customer become eligible to review the supplier and the purchased products (FR-ECOM-022, FR-ECOM-023).

---

## Notifications

The source lists these notification triggers for the module (exact delivery channel — push/SMS/email — is not specified per notification):

New Order, Successful Payment, Payment Failure, Order Confirmed, Order Preparing, Order Ready for Delivery, Order Out for Delivery, Delivery OTP Required, Order Completed, Order Cancelled, Refund Processed, Wallet Balance Available, Withdrawal Requested, Withdrawal Approved, Withdrawal Rejected, Withdrawal Paid, New Supplier Review, New Product Review.

## Data model (tables proposed in the source)

Not yet present in the repo (`Modules/ECommerce/database/migrations` is currently empty). Listed here as the source's proposed table names, not a confirmed schema:

`supplier_stores`, `products`, `product_categories`, `product_subcategories`, `product_images`, `product_attributes`, `product_attribute_values`, `product_variants`, `carts`, `cart_items`, `checkouts`, `orders`, `order_items`, `order_status_history`, `order_delivery_otps`, `payments`, `payment_transactions`, `supplier_wallets`, `supplier_wallet_transactions`, `customer_wallets`, `customer_wallet_transactions`, `withdrawal_requests`, `supplier_reviews`, `product_reviews`.

**Note on wallet/payment ownership:** the source groups `payments`, `payment_transactions`, `supplier_wallets`, and `customer_wallets` under the ECommerce module's own tables. This repo's architecture (`docs/architecture/module-boundaries.md`) instead has `Modules/Core` own platform-wide "wallet/finance primitives," with business modules consuming them through Core contracts/events. Whether ECommerce's supplier/customer wallets are Core wallet primitives directly, or a marketplace-specific ledger built on top of Core's finance contracts, is not resolved by the source and should be treated as an open design decision, not assumed either way.

## API endpoints (as illustrated in the source)

The source lists illustrative endpoints under a bare `/ecommerce/...` path (no version/`api` prefix shown in the endpoint list itself, unlike the `/api/v1/ecommerce/*` API Group value given at the top of the module). The actual current mount point in this repo is `/api/v1/ecommerce` (see `Modules/ECommerce/app/Providers/RouteServiceProvider.php` and `routes/api.php`, both still an empty scaffold as of this writing):

```
GET    /ecommerce/products
GET    /ecommerce/products/{id}
POST   /ecommerce/products
PUT    /ecommerce/products/{id}
DELETE /ecommerce/products/{id}
POST   /ecommerce/cart/items
PUT    /ecommerce/cart/items/{id}
DELETE /ecommerce/cart/items/{id}
GET    /ecommerce/cart
POST   /ecommerce/checkout
GET    /ecommerce/orders
GET    /ecommerce/orders/{id}
PUT    /ecommerce/orders/{id}/status
POST   /ecommerce/orders/{id}/cancel
POST   /ecommerce/orders/{id}/delivery/verify
GET    /ecommerce/wallet
GET    /ecommerce/wallet/transactions
POST   /ecommerce/withdrawals
GET    /ecommerce/withdrawals
POST   /ecommerce/orders/{id}/supplier-review
POST   /ecommerce/order-items/{id}/product-review
```

This list is illustrative of the required capabilities, not a final route contract — no category, variant, or store-management endpoints are listed despite those being covered by dedicated FRs, so treat it as incomplete rather than authoritative.

## Edge cases called out in the source

- Product becomes unavailable during checkout.
- Product price changes while stored in cart.
- One supplier's items become unavailable while other suppliers' items in the same cart remain available.
- Payment succeeds but order creation partially fails.
- Duplicate payment callback is received.
- Customer requests cancellation immediately before the supplier changes the order to Preparing (race condition on the cancellation cutoff).
- Incorrect delivery OTP entered repeatedly.
- Supplier loses E-Commerce subscription while active orders remain open.
- Supplier wallet is restricted while orders are pending.
- Refund occurs after commission calculation (mechanics not specified — see step 9 of the end-to-end flow above).
- Withdrawal request is submitted before the settlement waiting period expires.
- Customer purchases the same product from multiple supplier orders.
- Supplier deletes or archives a product after it has already been purchased.

None of these edge cases have a prescribed resolution in the source; they are flagged as things the implementation must handle, with the actual handling left to design.
