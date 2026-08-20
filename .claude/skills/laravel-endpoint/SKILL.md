---
name: laravel-endpoint
description: Scaffold a new Umrany API endpoint (Controller + FormRequest + API Resource + route + Scramble annotations + Pest test) following project conventions. Use when adding any new API route to a module.
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

## 5. Document it for Scramble

`dedoc/scramble` infers most of the shape automatically from the FormRequest and Resource — don't
re-describe what it already infers. Two things to actually add:

1. A class-level group attribute, once per controller (skip if the controller already has one):

   ```php
   #[\Dedoc\Scramble\Attributes\Group('<Module> / <Entity>', weight: N)]
   final class <Name>Controller extends Controller
   ```

2. In the FormRequest's `rules()`, a `/** ... */` doc-comment directly above any field whose name
   alone doesn't make its meaning/format obvious (skip fields like `name`/`status` that don't need
   one):

   ```php
   /**
    * Short client-facing description.
    *
    * @example value
    */
   'field_name' => ['required', 'string'],
   ```

Only reach for a hand-written `#[\Dedoc\Scramble\Attributes\Response(status, description:,
examples:)]` on the controller method when a response's meaning can't be inferred — typically a
message added via the FormRequest's `after()` closure rather than `rules()`, or one thrown by a
Service method (prefer a `@throws` PHPDoc tag on that Service method first; Scramble reads through
one level of method calls). No `@authenticated` tag needed — bearer-auth requirements are derived
automatically from the route's `auth:sanctum` middleware.

Scramble auto-matches every route under `api/*` (`config('scramble.api_path')`) — no per-module
config needed. Nothing to regenerate — spot-check `/docs`, which is generated live on every
request. If you edited a docblock/attribute while the stack was already running, reload Octane
first (root `CLAUDE.md` Rule 4) or the change won't show up.

## 6. Update the module docs (not optional — root `CLAUDE.md` Rule 5)

- If this endpoint introduces a new entity/field, add it to `Modules/<Module>/CLAUDE.md`'s entity list **and** `docs/modules/<module-alias>.md`.
- If this endpoint establishes a new *pattern* (not just another CRUD endpoint using existing conventions — e.g. a new pagination style, a new error shape, a new auth flow), update `docs/api/conventions.md` too.
- If it's genuinely just one more endpoint following patterns already documented, no doc change is needed beyond the Scramble annotations from step 5 — don't pad docs with restating what's already covered.

## 7. Test it

Write the Pest feature test in the generated `<Name>Test.php` (`Modules/<Module>/tests/Feature/`): happy path, one authorization-denied case, one validation-failure case. Run:

```bash
docker compose exec app ./vendor/bin/pest --filter=<Name>Test
```

## 8. Before considering it done

- `composer lint` (Pint) and `composer analyse` (Larastan) clean.
- No direct cross-module model usage introduced (step 1 rule).
- If this endpoint reads/writes anything cacheable, follow the `umrany:<module>:<entity>:<id>` key convention from root `CLAUDE.md`.
- Docs from step 6 actually updated, not just considered — run `.claude/skills/docs-sync-check` if unsure.
- `/docs` shows the endpoint with a sensible group, description, and request/response shape (spot-check after an Octane reload).
