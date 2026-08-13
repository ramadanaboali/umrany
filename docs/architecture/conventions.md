# Coding Conventions

These are the conventions every module — Core and all four business modules — follows. They're enforced through a mix of tooling (Pint, Larastan) and review discipline (the rest); none of them are module-specific.

## Strict types

Every new PHP file starts with `declare(strict_types=1);`. No exceptions for "small" files or DTOs — this is a blanket rule across all five modules, not a per-module choice.

## Style and static analysis

- **Style**: `laravel/pint`, run via `composer lint`. Run before every commit; CI expects a clean Pint pass.
- **Static analysis**: `larastan/larastan`, run via `composer analyse`. Must be clean before merge — treat a new Larastan error the same as a failing test, not as something to suppress with a baseline entry unless there's no other option.

## Controller pattern: FormRequest → Service (→ Repository) → Resource

Controllers stay thin and contain no business logic and no validation logic. Full rationale and
the Gateway/Orchestration/Service/Repository mapping: `docs/architecture/backend-layering.md`. The
shape every endpoint follows:

1. **FormRequest** — owns all input validation and any request-level authorization gate. Controllers never call `$request->validate(...)` inline; if a route accepts input, there's a dedicated FormRequest class for it.
2. **Service class** — owns the actual business logic (a single clearly-named entry point method per operation, e.g. `AuthService::register()`). Controllers call into a Service and do nothing else; this is what makes business logic testable independent of HTTP and reusable from queued jobs or console commands. A Service calls a **Repository** (bound via a `Contracts\...RepositoryInterface`, see `backend-layering.md`) for persistence — it never runs Eloquent queries inline for anything a Repository already owns.
3. **API Resource** — owns response shaping. Controllers never return raw Eloquent models or hand-built arrays; every response is transformed through an `Illuminate\Http\Resources\Json\JsonResource` (or a `spatie/laravel-data` DTO where the endpoint already has one).

A controller method, in the common case, is just: resolve the FormRequest, call the Service, return the Resource. If a controller method is doing anything more than that, the logic belongs in a Service instead. (Older code may still reference `Actions/*` — that's the pre-refactor name for this same layer; see `backend-layering.md`'s migration note.)

## Authorization: Policies + spatie/laravel-permission

Authorization goes through Laravel Policies backed by `spatie/laravel-permission` roles and permissions. This means `$this->authorize('update', $order)` backed by a Policy method that checks `$user->can('...')`, not a raw role check.

**Never** compare role strings directly:

```php
// wrong — bypasses Policies, hardcodes role names, ignores permissions entirely
if ($user->role === 'admin') {
    // ...
}
```

Instead, gate through a Policy method that itself checks permissions:

```php
// right
public function update(User $user, Order $order): bool
{
    return $user->can('orders.update') || $user->id === $order->owner_id;
}
```

This keeps authorization logic in one place per model (the Policy), testable in isolation, and changeable (adding a new role, splitting a permission) without touching controllers.

**Exception — pure permission checks with no object-level nuance.** A Policy exists to combine a
permission check with per-instance logic (`$user->can('orders.update') || $user->id === $order->owner_id`
above). When a route's authorization is *only* "does this admin have this permission" — no
ownership, no per-object exception — a Policy class would just proxy to `$user->can(...)` with no
added logic, which is unnecessary ceremony (`CLAUDE.md` Rule 3: don't build abstractions the
current task doesn't need). The admin dashboard's role/admin-management routes
(`routes/admin.php`) use Spatie's `can:<permission>` route middleware directly for exactly this
reason. Reach for a Policy the moment there's real per-instance logic to add, not before.

## Testing: Pest

Tests are written in Pest (`pestphp/pest` + `pestphp/pest-plugin-laravel`). **Every new endpoint ships with at least one feature test** covering its primary success path; endpoints with meaningfully different authorization or validation branches get a test per branch, not just one happy-path test. Run the suite with `./vendor/bin/pest` (or `composer test` if wired up) before opening a PR.

## API documentation: Scribe doc-blocks are mandatory

Every new endpoint needs Scribe-compatible doc-blocks on its controller method (`@group`, `@bodyParam`, `@response`, and any relevant `@authenticated`/`@urlParam` annotations) — not as an afterthought, but as part of shipping the endpoint. Scribe generates `/docs`, `/docs.openapi`, and `/docs.postman` directly from the registered route list and these doc-blocks (see `tech-stack.md` for why Scribe was chosen); an endpoint without doc-blocks either doesn't show up correctly in those outputs or shows up with no usable description, which defeats the point of having docs generated from the actual routes instead of hand-maintained separately. Regenerate with `php artisan scribe:generate` after adding or changing any documented route.

## Practical shortcut

The `.claude/skills/laravel-endpoint` skill scaffolds a new endpoint following all of the above in one pass — Controller, FormRequest, Action, Resource, route registration, Scribe doc-block, and a starter Pest test — so that a new endpoint conforms by construction rather than by post-hoc review.
