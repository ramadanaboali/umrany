# 23. Scramble over Scribe for API documentation

## Status

Accepted (supersedes [0003-scribe-over-l5swagger.md](0003-scribe-over-l5swagger.md))

## Context

`knuckleswtf/scribe` (chosen in ADR 0003 over `darkaonline/l5-swagger`, specifically for
introspecting the live route list instead of scanning annotated directories) worked, but every
endpoint still needed hand-written `@bodyParam`/`@response` doc-blocks kept in sync by hand, and
its `ResponseCalls` strategy (firing real HTTP requests to capture example responses) failed its
dry run for every file-upload endpoint and two `PUT` routes hitting a null relation on Scribe's
synthetic test user — a known, harmless, but permanent wart (`Modules/Core/CLAUDE.md`'s "Known
minor gap").

`dedoc/scramble` documents an API primarily via **static analysis** — inferring request/response
shapes from FormRequest `rules()` and Resource `toArray()` bodies, adding hand-written annotations
only where inference genuinely can't reach (an ad-hoc `response()->json([...], 422)` whose meaning
isn't inferable, a Service-thrown exception one level below the controller).

## Decision

Replace `knuckleswtf/scribe` with `dedoc/scramble` (`^0.13.41` — earlier 0.13.x releases cap at
Laravel 12; 0.13.41 is the first to declare Laravel 13 support).

- **Route matching stays prefix-based against `api/*`** (`config('scramble.api_path')`, default
  `'api'`) — the exact same structural property ADR 0003 valued in Scribe: zero per-module config,
  works the moment any module registers a route under `api/*`. Verified directly: the Site Settings
  feature's new `GET /api/v1/core/settings` endpoint appeared in `/docs` with no Scramble config
  change at all.
- **`@group` → `#[Group('X / Y', weight: N)]`** on the controller class (Scramble tags by
  controller by default; the attribute preserves the existing group names/ordering). `@authenticated`
  is deleted entirely — `config('scramble.security_strategy')` is set to
  `MiddlewareAuthSecurityStrategy::class`, which derives `security` per-operation from each route's
  `auth:sanctum` middleware (its default pattern `['auth', 'auth:*']` already matches via
  wildcard) and applies a global bearer `securityScheme` the moment any documented route needs it.
  Verified: public routes (`register`, `login`, `countries`, the new `settings` endpoint) show
  `"security": []`; authenticated ones inherit the global bearer requirement.
- **`@bodyParam`/`@urlParam` → deleted**; the description + example move to a `/** ... */`
  doc-comment directly above the corresponding rule inside the FormRequest's `rules()` method.
  Verified this survives Scramble's analysis intact (checked the generated `RegisterRequest`
  schema field-by-field) — with one gap: `required_without:mobile`/`required_without:email` isn't
  a Scramble-recognized rule for inferring the schema's `required` list, so that constraint's
  explanation stays in the moved doc-comment text rather than becoming a structural
  `oneOf`/`required` guarantee.
- **`@response` → mostly deleted** (inferred automatically from literal
  `response()->json([...], $status)` bodies and from Resource `toArray()` shapes — verified, e.g.,
  file-upload endpoints correctly show `multipart/form-data`, and `AuthController::login()`'s two
  distinct 200 shapes correctly produce an `anyOf`). Kept as hand-written
  `#[Response(status, description: ..., examples: [...])]` only for the small number of cases where
  the message text comes from a FormRequest `after()` closure Scramble's static analysis can't see
  into (e.g., `ProviderController::store()`'s "already has a provider profile" message, added via
  `ActivateProviderRequest::after()`, not `rules()`). Where the equivalent message comes from a
  `ValidationException` thrown one Service method below the controller, a `@throws` PHPDoc tag on
  that Service method was added instead of a controller-level attribute — Scramble reads through
  one level of method calls for exception-driven responses.
- **No more Scribe-style live response capture, and no more dry-run failures to work around.**
  Scramble performs zero HTTP calls; `Modules/Core/CLAUDE.md`'s "Known minor gap" paragraph is
  deleted outright rather than rewritten, since the failure mode it documented cannot recur.
- **Docs URL kept at `/docs`** (`Scramble::configure()->expose(ui: '/docs', document:
  '/docs/openapi.json')` in `AppServiceProvider::boot()`) rather than Scramble's own default
  (`/docs/api`), so every existing doc/skill reference to `/docs` stays correct.
- **Docs route gated by a `viewApiDocs` Gate** (`Gate::define('viewApiDocs', fn () =>
  ! app()->isProduction())`), via Scramble's own `RestrictedDocsAccess` middleware — stricter than
  Scribe's previous no-gate-at-all default, at zero cost in every non-production environment
  (including `testing`, which the new `ApiDocumentationTest` needs).
- **No regeneration step, and no committed intermediate artifacts.** `/docs/openapi.json` is
  generated fresh on every request; `.scribe/`, `storage/app/private/scribe/`,
  `resources/views/scribe/`, and `config/scribe.php` were deleted outright (the last one
  specifically has to be deleted, not just left — it references `Knuckles\*` classes that no
  longer exist and breaks `config:cache`/`package:discover` the moment the package is removed if
  left in place; confirmed by doing exactly that during this migration). `php artisan
  scramble:export` exists only for handing a static spec to an external client/SDK generator, not
  as part of the normal dev loop.

## Consequences

- **Postman collection support is lost** — Scribe's `/docs.postman` has no Scramble equivalent.
  Mitigation: Postman (and most tools) can import an OpenAPI spec directly from a URL —
  `/docs/openapi.json` is the import source now.
- **OpenAPI version moves from 3.0.3 to 3.1.0.** JSON-Schema-compatible and strictly more
  expressive, but worth knowing if a future SDK-generation tool only supports 3.0.
- **`spatie/laravel-query-builder`'s `filter[...]`/`sort`/`include` query parameters have no
  first-class Scramble support on the OSS tier** (that support is a Scramble PRO feature). Not a
  regression today — no controller in this codebase uses `QueryBuilder::for(...)` yet — but the
  moment one does, its query parameters will need hand-written `#[QueryParameter]` attributes to
  show up in `/docs`, where Scribe would have needed the same manual work anyway. `spatie/laravel-data`
  objects returned directly from a controller (bypassing a Resource) get the same treatment — one
  endpoint in this codebase does this today (`Scramble's export warned "1 endpoint returns Laravel
  Data objects"`); its schema still generates, just less precisely than a Resource-wrapped response.
- **Docblock/attribute edits are not reflected until the Octane worker restarts**, same as any
  other code change (root `CLAUDE.md` Rule 4) — `octane:reload` (not always reliable per that
  rule) or `docker compose restart app`, verified necessary during this migration (a route added
  without a reload produced a `RouteNotFoundException` from a still-old worker).
- Every doc/skill file that named Scribe by name was updated in the same change: root `CLAUDE.md`,
  `docs/api/conventions.md`, `docs/architecture/conventions.md`,
  `docs/architecture/system-architecture.md`, `docs/architecture/tech-stack.md`, `README.md`,
  `.claude/skills/laravel-endpoint/SKILL.md`, `.claude/skills/docs-sync-check/SKILL.md`,
  `Modules/Core/CLAUDE.md`.
