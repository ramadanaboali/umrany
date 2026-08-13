# Backend Layering: Gateway, Orchestration, Services, Repositories

This is the answer to "how do Gateway/Orchestrator/Service/Repository concepts map onto this
codebase" and the concrete convention every module follows for organizing business logic. It
supersedes the older, simpler "FormRequest → Action → Resource" description in
`conventions.md` — that document now points here.

## The four layers, and what plays each role in this app

| Concept | What plays this role here | Responsibility |
|---|---|---|
| **Gateway** | nginx → Octane/RoadRunner → Laravel's route + middleware pipeline (`auth:sanctum`/`auth:admin`, `module.entitlement:<alias>`, `account.verified`, `can:<permission>`, `throttle:<limiter>`) | The single entry point every request passes through. Authentication, authorization, entitlement, and rate limiting are all resolved here, before a controller ever runs — see `system-architecture.md` and `infrastructure.md` for the actual process topology. |
| **Orchestration** | The `Http\Controllers\*` methods themselves | Thin coordination only: resolve the FormRequest, call one Service method, wrap the result in a Resource. A controller method is never more than a few lines; if it's doing real work, that work belongs one layer down. |
| **Service** | `app/Services/*` (root) and `Modules/<Name>/app/Services/*` | Business logic — the actual rules, multi-step workflows, and cross-cutting concerns (caching, dispatching events/notifications). Calls one or more Repositories for persistence and other Services for side effects it needs (e.g. `AuthService` calling `IssueVerificationCode`-equivalent logic, or a caching decorator invalidating a cache key after a write). This is what used to live in `Actions/*` classes — see "Migration note" below. |
| **Repository** | `Modules/<Name>/app/Repositories/*`, bound via a `Contracts/...RepositoryInterface` in the module's service provider | Pure data access — the only place that runs Eloquent queries for a given aggregate (`UserRepository`, `AdminRepository`, `ProviderRepository`, ...). No business rules live here: a repository answers "find X" / "persist Y", nothing about whether that's currently allowed. |

Cross-cutting concerns (auth, authorization, caching, logging) are handled at the layer where they
naturally belong, not duplicated everywhere:

- **Authentication/authorization** — entirely at the Gateway layer (middleware). Services and
  Repositories never re-check "is this user allowed" — by the time a Service method runs, the
  Gateway has already established who the caller is and that they're allowed to hit this route.
  The one exception is business-rule authorization that depends on *data* the Gateway can't see
  (e.g. "is this the last active Super Admin" in `Services\Admin\AdminManagementService`) — that's
  a business rule, not an auth rule, and lives in the Service.
- **Caching** — a Service's responsibility, applied around its own Repository calls (cache-aside:
  check cache, fall through to Repository on miss, write back). See `Services\CapabilityService`
  for the concrete pattern, and `module-boundaries.md` § User capability resolution for why this
  particular value is cached. A Repository is never cache-aware — it always reflects the database
  as of the call.
- **Logging** — Laravel's standard channels (`storage/logs`, viewable at `/log-viewer` per
  `infrastructure.md`), written from Services at meaningful business-event boundaries (a
  significant state transition, a failure worth investigating), not from Repositories or
  Controllers.

## Concrete example: registration

```
POST /api/v1/core/auth/register
  → Gateway: throttle:login, api middleware group
  → AuthController::register(RegisterRequest $request)      [Orchestration]
      → AuthService::register($request->validated())         [Service — business logic]
          → UserRepository::create(...)                       [Repository — persistence]
          → UserProfileRepository::createForUser(...)         [Repository — persistence]
          → IssueVerificationCode notification dispatch        [Service — side effect]
      ← returns [User, NewAccessToken]
  ← UserResource + token, wrapped in the standard {"data": {...}} envelope
```

## Repository interfaces are bound like every other Contract

Same pattern already established for `ModuleEntitlementChecker`/`UserCapabilityResolver`: an
interface in `Repositories/Contracts/` (or `Contracts/` for module-wide concerns), a concrete
Eloquent-backed implementation in `Repositories/`, bound in the module's `ServiceProvider::register()`.
Services type-hint the interface, never the concrete class — this is what keeps a Repository
swappable in tests (bind a fake in-memory implementation) without touching the Service.

## Enums and Data (DTOs) are standard, not incidental

`Enums/` (backed PHP enums — `UserStatus`, `ProviderVerificationStatus`, ...) and `Data/`
(`spatie/laravel-data` DTOs — `UserCapabilities`) are the default way to represent, respectively, a
closed set of named states and a typed value object crossing a boundary (Service → Controller,
Service → cache, Service → another Service). Prefer a backed enum over a raw string constant for
any column with a fixed set of valid values; prefer a `Data` class over a bare associative array
for any value with more than one or two fields that crosses a method boundary more than once.

## Migration note (why this replaced `Actions/*`)

The project started with `Http/Controllers → FormRequest → Actions/*Action → Resource`. That
worked for a single business-logic step per endpoint, but had no natural home for a Repository
layer, for caching, or for logic shared across what used to be several separate Action classes
(e.g. issuing a verification code was needed by both registration and password reset). Renaming
`Actions/*` to `Services/*` and introducing `Repositories/*` underneath resolved that without adding
a redundant fourth layer — a Service **is** the orchestrator for its own business flow; there's no
separate "Orchestrator" class type distinct from a Service.

If you find an existing `Actions/*` reference anywhere, it's stale — the class has moved to
`Services/*` with the same method name and behavior; update the reference and this note both stay
accurate (`CLAUDE.md` Rule 5).
