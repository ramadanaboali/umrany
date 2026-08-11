# 1. Modular Monolith via nwidart/laravel-modules

## Status

Accepted

## Context

Umrany covers four distinct products — a project bidding platform, a construction materials marketplace, a lightweight ERP, and AI-assisted decision support — plus shared platform services (auth, subscriptions, admin, and more), all sharing one user account and one database. A true microservices split would add operational complexity (service discovery, distributed transactions, network calls in place of what are currently in-process operations) without a corresponding need: there is a single team and a single deploy target at this stage. Conversely, a flat, unstructured Laravel app would let module boundaries erode over time, since nothing would stop one product area's code from reaching into another's.

## Decision

Use a modular monolith, not microservices and not a plain single-app Laravel structure, organized into five modules — Core, Projects, ECommerce, ERP, AI — via `nwidart/laravel-modules` (v13, confirmed compatible with Laravel 13 / PHP 8.4). Each module owns its own routes, migrations, config, and service provider. Cross-module dependencies are restricted to Core: a module may depend on Core's contracts and events, and two business modules never reach into each other's Eloquent models directly. See `docs/architecture/module-boundaries.md` for the full dependency rule and examples.

## Consequences

- Module boundaries are enforced by convention and code review, not by a hard runtime boundary (no separate process, network call, or build-time restriction stops a stray cross-module import from compiling). The `.claude/skills/module-boundary-check` skill exists specifically to catch this at review time, and discipline is required from every contributor.
- A known rough edge: nwidart module singleton services registered in a module's service provider have had container-registration-order issues under certain conditions (see nWidart/laravel-modules GitHub issue #1761), which compound under Octane's persistent workers (see ADR 0002). This is mitigated by preferring `scoped()` over `singleton()` bindings in module service providers.
- The one-way Core dependency (everything depends on Core, Core depends on nothing) keeps the module graph a tree rather than a mesh, and is what would allow Core to be split out as its own service first, should the platform ever need to move off the monolith.
