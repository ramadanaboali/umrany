---
name: module-boundary-check
description: Pre-merge check for cross-module boundary violations in the Umrany modular monolith — one business module reaching directly into another's Eloquent models/namespace instead of going through Modules/Core contracts or events. Run before opening a PR that touches more than one module, or when asked to review module boundaries.
---

# Check module boundaries before merging

Umrany's rule (root `CLAUDE.md`): `Modules/Core` is the only module every other module may depend on directly. `Projects`, `ECommerce`, `ERP`, and `AI` never reference each other's classes directly — only through `Modules/Core/app/Events` (domain events) or an explicit `Contracts` interface the owning module binds and exposes.

## 1. Find cross-module references

For each business module, grep for `use` statements pointing at a sibling business module's internal namespaces (not `Contracts`):

```bash
for m in Projects ECommerce ERP AI; do
  echo "=== violations reachable from $m ==="
  grep -rn "use Modules\\\\" Modules/$m/app --include="*.php" \
    | grep -v "Modules\\\\$m\\\\" \
    | grep -v "Modules\\\\Core\\\\" \
    | grep -v "\\\\Contracts\\\\"
done
```

Any hit is a candidate violation: module `$m` importing a concrete class (Model, Service, Action, Controller) from another business module.

## 2. Judge each hit

Not everything that matches is a real violation — check case by case:

- **Real violation**: `use Modules\ECommerce\Models\Order` inside `Modules/ERP` — direct Eloquent reach-across. Fix: define/use a `Modules\ECommerce\Contracts\OrderReader` interface (bound in `ECommerceServiceProvider`), or replace the coupling with a domain event (`Modules\Core\Events\OrderCompleted`) that ERP listens for.
- **Acceptable**: importing a `Contracts\...` interface from another module — that's the sanctioned extension point.
- **Acceptable**: both modules depending on the same `Modules\Core\...` class — that's expected, Core is the shared dependency.

## 3. Also check routes and service providers

Cross-module leaks aren't always in `app/` — check:

```bash
grep -rln "Modules\\\\ECommerce\|Modules\\\\Projects\|Modules\\\\ERP\|Modules\\\\AI" Modules/*/routes Modules/*/app/Providers --include="*.php"
```
Same judgment as step 2 applies.

## 4. Report

For each real violation found, state: the file/line, which module boundary it crosses, and the concrete fix (which `Contracts` interface to introduce, or which `Core` event to emit/listen for instead). Don't just say "this violates the boundary" — name the interface or event that should exist.

## 5. If you're the one introducing the coupling

Before writing code that would trigger this check, ask: does this belong in `Modules/Core` instead? If two business modules both need the same concept (e.g. "wallet balance", "verified provider status"), that's a signal the concept is platform-shared and belongs in Core, not that one module should import the other.
