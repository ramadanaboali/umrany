---
name: laravel-endpoint
description: Scaffold a new Umrany API endpoint (Controller + FormRequest + API Resource + route + L5-Swagger OpenAPI attributes, Pest test only for critical paths) following project conventions. Use when adding any new API route to a module.
---

# Scaffold a new API endpoint

Given a module name, an entity/action, and the HTTP verb+path, produce all of the following — never just the controller.

## 1. Clarify placement

- Which module owns this? (`Core`, `Projects`, `ECommerce`, `ERP`, `AI` — see root `CLAUDE.md` module map). If it's unclear, check `Modules/<X>/CLAUDE.md` entity lists before guessing.
- Does this need data from another module? If so, it must go through that module's `Contracts` interface or a domain event — never a direct cross-module Eloquent call. Run `.claude/skills/module-boundary-check` after writing the code.

## 2. Generate the pieces (module-scoped artisan generators)

```bash
docker compose exec app php artisan module:make-controller <Name>Controller <Module> --api
docker compose exec app php artisan module:make-request <Name>Request <Module>
docker compose exec app php artisan module:make-resource <Name>Resource <Module>
docker compose exec app php artisan module:make-test <Name>Test <Module> --pest
```

## 3. Wire the controller

- `declare(strict_types=1)`.
- Controller method signature: `(<Name>Request $request)` — all validation lives in the FormRequest, never inline `$request->validate()`.
- Business logic goes in a Service class (`Modules/<Module>/app/Services/`) if it's more than a trivial query — controllers call the service and return a Resource. Don't invent a Service for a one-line passthrough. See `docs/architecture/backend-layering.md` for the full Gateway/Service/Repository shape.
- If the Service needs persistence beyond a single trivial lookup, it calls a Repository (`Modules/<Module>/app/Repositories/`, bound via a `Contracts\...RepositoryInterface`) rather than querying Eloquent inline.
- Authorize via a Policy (`$this->authorize(...)`), route-level `can:<permission>` middleware for pure permission checks with no per-object nuance, or a `spatie/laravel-permission` gate check — never role-string comparisons.
- Return via the generated API Resource, not raw Eloquent models or arrays.

## 4. Register the route

Add it inside `Modules/<Module>/routes/api.php`, inside the existing `Route::prefix('v1/<module-alias>')->name('<module-alias>.')->group(...)` block. Follow the existing REST naming inside that group.

## 5. Document it with L5-Swagger (ARCH-API, decided 2026-11-11 — replaces Scramble)

L5-Swagger (`darkaonline/l5-swagger`, swagger-php **PHP 8 attributes**, never docblock annotations) is installed by
NFR.19 at the start of UMR-004. Until then Scramble still serves `/docs`; write the attributes anyway.

- **Operation** on the controller method: `#[OA\Get(path: '/api/v1/<alias>/...', tags: ['<Module>/<Resource>'], ...)]`
  (+ `security: [['sanctum' => []]]` for protected routes; reference shared parameters `X-Acting-Context`, `Accept-Language`).
- **Request schema** on the FormRequest class (`#[OA\Schema]` with properties + examples); **response schema** on the API
  Resource; envelope/pagination/error/security components live once in `app/OpenApi/`.
- **Which document:** user/public routes → `user` doc; `/api/v1/admin/*` → `admin` doc.
- Generate + check: `docker compose exec app php artisan l5-swagger:generate --all` and `php artisan api:docs-coverage`
  (fails if any api route has no operation). Then `composer api:sync` to refresh the Postman collections in
  `/Users/ramadan/Umrany/postman/`.
- Lookups in requests: validate with `Rule::exists('lkp_<x>', 'code')` and document the allowed codes by reference to the
  lookup (never paste the workbook list into the schema — it would drift).

## 6. Update the module docs (not optional — root `CLAUDE.md` Rule 5)

- If this endpoint introduces a new entity/field, add it to `Modules/<Module>/CLAUDE.md`'s entity list **and** `docs/modules/<module-alias>.md`.
- If this endpoint establishes a new *pattern* (not just another CRUD endpoint using existing conventions — e.g. a new pagination style, a new error shape, a new auth flow), update `docs/api/conventions.md` too.
- If it's genuinely just one more endpoint following patterns already documented, no doc change is needed beyond the OpenAPI attributes from step 5 — don't pad docs with restating what's already covered.

## 7. Verify it (tests only for critical paths)

Test policy: write a Pest test **only** when the endpoint is a critical path — money/ledger, a
multi-transition state machine, access-control isolation (acting context, entitlement, company
groups, admin permission), or an OTP/confirmation flow — or when the plan's story lists a
critical-path test task. Skip the `module:make-test` step otherwise.

Always verify manually: curl the endpoint for the happy path, one denied case and one invalid case
(after `docker compose restart app`), and check the response envelope and status codes. If a test
was written: `docker compose exec app ./vendor/bin/pest --filter=<Name>Test`.

## 8. Before considering it done

- `composer lint` (Pint) and `composer analyse` (Larastan) clean.
- No direct cross-module model usage introduced (step 1 rule).
- If this endpoint reads/writes anything cacheable, follow the `umrany:<module>:<entity>:<id>` key convention from root `CLAUDE.md`.
- Docs from step 6 actually updated, not just considered — run `.claude/skills/docs-sync-check` if unsure.
- `/docs` shows the endpoint with a sensible group, description, and request/response shape (spot-check after an Octane reload).
