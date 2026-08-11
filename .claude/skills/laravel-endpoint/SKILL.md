---
name: laravel-endpoint
description: Scaffold a new Umrany API endpoint (Controller + FormRequest + API Resource + route + Scribe doc-block + Pest test) following project conventions. Use when adding any new API route to a module.
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
- Business logic goes in an Action class (`Modules/<Module>/app/Actions/`) if it's more than a trivial query — controllers call the action and return a Resource. Don't invent an Action class for a one-line passthrough.
- Authorize via a Policy (`$this->authorize(...)`) or a `spatie/laravel-permission` gate check — never role-string comparisons.
- Return via the generated API Resource, not raw Eloquent models or arrays.

## 4. Register the route

Add it inside `Modules/<Module>/routes/api.php`, inside the existing `Route::prefix('v1/<module-alias>')->name('<module-alias>.')->group(...)` block. Follow the existing REST naming inside that group.

## 5. Document it for Scribe

Add doc-block annotations directly above the controller method:

```php
/**
 * Short summary of what this endpoint does.
 *
 * @group <Module> / <Entity>
 * @authenticated
 * @bodyParam field_name string required Description. Example: value
 * @response 200 scenario="success" {"data": {...}}
 */
```

Scribe auto-matches every route under `api/*` (see `config/scribe.php`) — no per-module config needed, just keep doc-blocks accurate. Regenerate locally with `docker compose exec app php artisan scribe:generate` and spot-check `/docs`.

## 6. Update the module docs (not optional — root `CLAUDE.md` Rule 5)

- If this endpoint introduces a new entity/field, add it to `Modules/<Module>/CLAUDE.md`'s entity list **and** `docs/modules/<module-alias>.md`.
- If this endpoint establishes a new *pattern* (not just another CRUD endpoint using existing conventions — e.g. a new pagination style, a new error shape, a new auth flow), update `docs/api/conventions.md` too.
- If it's genuinely just one more endpoint following patterns already documented, no doc change is needed beyond the Scribe doc-block from step 5 — don't pad docs with restating what's already covered.

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
