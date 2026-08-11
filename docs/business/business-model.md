# Umrany Business Model

Source: Umrany System Analysis & Software Requirements Specification, Chapter 1, Section 7 ("Business Model"), with supporting detail from Chapter 2 (E-Commerce settlement mechanics) and Chapter 4 (Supplier/ERP User access rules).

## Summary

Umrany follows a Software-as-a-Service (SaaS) business model that combines subscription-based revenue with transaction-based revenue. There are four revenue components described in the source material:

1. Subscription plans for service providers (the primary revenue source).
2. An independently subscribable ERP module.
3. An independently subscribable E-Commerce module.
4. Transaction commissions on marketplace purchases, collected together with payment gateway fee deductions.

Plus a set of future revenue opportunities not yet part of the core model.

## 1. Subscription Plans for Service Providers (Primary Revenue Source)

The primary source of revenue comes from subscription plans designed for service providers. These subscriptions provide different platform capabilities, such as:

- Submitting additional project offers.
- Profile verification.
- Featured listings.
- ERP access.
- Marketplace access.
- Other premium features.

Note on plan design: the source material is explicit that offer limits (and by extension other capability gates) must not be hard-coded to fixed plan names — they are read dynamically from the active subscription's configured features. This is a business/product design decision, not just a technical implementation detail: it implies subscription plans are meant to be reconfigurable by platform administrators without requiring new plan types to be built into the system.

## 2. Independent ERP Subscription

The ERP module can be subscribed to independently, allowing a service provider to use the business management system regardless of their marketplace subscription plan. This means ERP access is not necessarily bundled — a provider could hold no marketplace-oriented subscription at all and still pay for ERP access alone.

An "ERP User" is defined as a Service Provider with active ERP access, and that access may originate from either the provider's main subscription plan or a standalone ERP subscription.

## 3. Independent E-Commerce Subscription

Similarly, suppliers may subscribe separately to activate the E-Commerce module and start selling products through the construction marketplace. A "Supplier" is defined as a Service Provider with active E-Commerce access, and that access may come from either a subscription plan that contains E-Commerce access, or a standalone E-Commerce subscription.

## 4. Marketplace Transaction Commission and Settlement Flow

The platform also generates revenue from transaction commissions applied to marketplace purchases. The settlement mechanic described in the source material:

- During each order, the platform collects the customer's payment first.
- Payment gateway fees are deducted.
- Platform commissions are deducted.
- The remaining balance is transferred to the supplier's wallet, according to the platform's configurable settlement policy.

This deduction order (payment gateway fee and commission removed before the supplier is credited) is described consistently in both Chapter 1 (business model) and Chapter 2 (E-Commerce platform description), so it can be treated as a firm business rule rather than an incidental detail.

Related structural notes:

- A single customer checkout spanning multiple suppliers is automatically split internally into supplier-specific orders, even though the customer only makes one payment.
- Settlement policy is explicitly configurable by platform administration (not a fixed percentage baked into the system).
- Suppliers hold a wallet and can request withdrawals; withdrawal approval is a sensitive administrative permission.
- Delivery/settlement is gated on order completion, confirmed via a One-Time Password (OTP) provided by the customer — this ties commission capture to confirmed fulfillment, minimizing platform liability for undelivered orders.

## Future Revenue Opportunities

The source material lists potential future revenue streams that are not part of the current defined model (mentioned only as future possibilities, not committed features):

- Premium advertising.
- Sponsored listings.
- AI-powered premium services.
- Enterprise subscriptions.
- Strategic partnerships.

These are noted in the requirements as directional only — the document does not specify pricing, mechanics, or timelines for any of them.
