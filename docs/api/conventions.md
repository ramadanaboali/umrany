# API Conventions

## Versioning and routing

All endpoints are namespaced `/api/v1/<module-alias>/...`, where `<module-alias>` matches the module's API prefix from the module map in the root `CLAUDE.md` (`core`, `projects`, `ecommerce`, `erp`, `ai`). For example, listing ECommerce orders is:

```
GET /api/v1/ecommerce/orders
```

Each module owns its own `routes/api.php` and declares its own `v1/<alias>` prefix inside that file — there is no central routes file listing every endpoint. See `docs/architecture/module-boundaries.md` for how routes are mounted per module.

## Authentication

Auth is bearer-token based via `laravel/sanctum`. Both first-party clients — the Next.js SPA and the Flutter mobile app — authenticate the same way:

```
Authorization: Bearer <token>
```

There is no cookie/session auth path for the API; Sanctum's SPA cookie mode is not used here, since both clients are separately-deployed apps talking to the API over HTTP, not a same-origin SPA. Tokens are issued by Core's auth endpoints and carry Sanctum abilities as needed.

## Authorization

Authorization is enforced via Laravel Policies backed by `spatie/laravel-permission` roles and permissions. Controllers call `$this->authorize(...)` (or `Gate`/policy methods) against permission checks — never raw role-string comparisons like `if ($user->role === 'admin')`. See the root `CLAUDE.md` golden rules.

## Filtering, sorting, and includes

Index endpoints that support filtering, sorting, or eager-loaded relations use `spatie/laravel-query-builder` rather than hand-parsing `$request->get(...)`. Standard query-builder conventions apply, e.g.:

```
GET /api/v1/ecommerce/orders?filter[status]=pending&sort=-created_at&include=items,customer
```

Each endpoint's controller/Action declares its own allowed filters, sorts, and includes — consult the endpoint's Scribe docs (below) for what's supported.

## Response shapes

All responses are built from Laravel API Resources. Controllers never return raw Eloquent models or collections — every payload passes through a `JsonResource` or `ResourceCollection` so the wire shape is explicit and stable independent of the underlying schema.

### Single resource

```json
{
  "data": {
    "id": 42,
    "type": "order",
    "status": "pending",
    "total": "1250.00",
    "created_at": "2026-08-01T09:15:00.000000Z"
  }
}
```

### Paginated collection

Paginated index endpoints use Laravel's default `AnonymousResourceCollection` wrapping a `LengthAwarePaginator`, giving the standard `data` / `links` / `meta` shape:

```json
{
  "data": [
    { "id": 42, "status": "pending", "total": "1250.00" },
    { "id": 41, "status": "completed", "total": "430.00" }
  ],
  "links": {
    "first": "https://api.umrany.com/api/v1/ecommerce/orders?page=1",
    "last": "https://api.umrany.com/api/v1/ecommerce/orders?page=8",
    "prev": null,
    "next": "https://api.umrany.com/api/v1/ecommerce/orders?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 8,
    "per_page": 15,
    "to": 15,
    "total": 112
  }
}
```

### Validation errors

Validation failures use Laravel's default 422 shape, produced automatically by FormRequests — no custom error formatting:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "quantity": ["The quantity field is required."],
    "email": ["The email must be a valid email address."]
  }
}
```

### Other errors

Any other error response (403, 404, 409, 500, etc.) is a plain message object with the appropriate HTTP status code:

```json
{
  "message": "This action is unauthorized."
}
```

Don't invent alternate error shapes (e.g. `{"error": {...}}` or a custom `code` field) for new endpoints — stay on these three shapes so clients can handle errors generically.

## Rate limiting

Laravel's default `throttle:api` middleware is present on the `api` middleware group for
everything. Named rate limiters exist for the auth-critical, guessable/enumerable endpoints —
registered in `app/Providers/AppServiceProvider::boot()`, applied per-route via
`throttle:<name>` in each module's `routes/api.php`:

| Limiter | Applies to | Limit | Keyed by |
|---|---|---|---|
| `login` | `POST /api/v1/core/auth/register`, `.../auth/login` | 5/minute | `login` input + IP |
| `admin-login` | `POST /admin/login` | 5/minute | email + IP |
| `verification-code` | `POST /api/v1/core/auth/resend-code` | 1/minute | authenticated user id |
| `verification-code-consume` | `POST /api/v1/core/auth/verify` | 5/minute | authenticated user id |
| `password-reset` | `.../auth/forgot-password`, `.../auth/reset-password`, `/admin/forgot-password`, `/admin/reset-password` | 3/minute | IP |

**Still open**: no other endpoint has a limiter beyond the generic `throttle:api` — read-heavy
browsing endpoints (master data, future Projects/ECommerce listing endpoints) and AI endpoints
still need traffic-shape-specific tuning once they exist. Don't assume a specific
requests-per-minute ceiling exists for anything not in the table above.

## Health checks

Three, deliberately not versioned/module-namespaced since they're for infrastructure, not clients:

| Endpoint | Purpose | Auth |
|---|---|---|
| `GET /up` | Laravel's built-in liveness probe — process booted, nothing more. | none |
| `GET /api/v1/core/health` | Liveness, JSON. | none |
| `GET /api/v1/core/health/ready` | Readiness — checks DB, Redis, and cache actually respond; `200` with per-check results, or `503` if any fail. | none |

Use `/health/ready` (not `/up`) for anything that should fail an orchestrator's readiness gate when the database or Redis is unreachable — `/up` only proves the framework booted.

## API documentation (Scribe)

API reference docs are auto-generated by `knuckleswtf/scribe` from the registered route list plus doc-blocks on controller methods — they are not hand-written or maintained as a separate reference. To keep `/docs` accurate:

- Add `@group`, `@bodyParam`, and `@response` doc-blocks to every new controller method.
- Regenerate after any route or doc-block change: `php artisan scribe:generate`.

Generated docs are served at:

| Path | Content |
|---|---|
| `/docs` | Interactive API documentation |
| `/docs.openapi` | OpenAPI 3 specification |
| `/docs.postman` | Postman collection |

See `docs/decisions/0003-scribe-over-l5swagger.md` for why Scribe was chosen over annotation-based tools.
