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

Each endpoint's controller/Action declares its own allowed filters, sorts, and includes — consult the endpoint's `/docs` entry (below) for what's supported. Note: `spatie/laravel-query-builder`'s `filter[...]`/`sort`/`include` query parameters aren't auto-documented by Scramble's OSS tier — a controller adopting `QueryBuilder::for(...)` needs hand-written `#[QueryParameter]` attributes for those to show up in `/docs` (see `docs/decisions/0023-scramble-over-scribe.md`).

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

### Auth session envelope (register / login / MFA challenge)

Every endpoint that actually issues a session (`POST /api/v1/core/auth/register`,
`.../auth/login`'s direct-success path, `.../auth/mfa/challenge`'s success path) returns the same
shape, built by `Modules\Core\Http\Resources\AuthPayloadResource`:

```json
{
  "data": {
    "user": { "id": 1, "name": "Ahmed", "email": "ahmed@example.com", "email_verified": false },
    "token": "1|abcdef...",
    "capabilities": {
      "is_provider": false,
      "provider_verified": false,
      "has_ecommerce_access": false,
      "has_erp_access": false,
      "max_project_offers": 3,
      "is_project_owner": true,
      "account_types": ["project_owner"]
    }
  }
}
```

The `provider` key is **absent** (not `null`) unless `capabilities.is_provider` is true — check
that field, don't check for the key's presence/absence as the signal itself.

When the account has MFA enabled, `POST /auth/login` instead returns, with no token issued yet:

```json
{ "data": { "mfa_required": true, "challenge_token": "...", "expires_in": 300 } }
```

See `docs/decisions/0012-totp-mfa-with-two-step-login.md`.

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
| `password-reset` | `.../auth/forgot-password`, `.../auth/reset-password`, `.../auth/password` (change), `.../auth/account` (delete), `/admin/forgot-password`, `/admin/reset-password` | 3/minute | IP |
| `mutations` | The general per-user write limiter — MFA management, profile/avatar/language/currency updates, notification preferences | 30/minute | authenticated user id (falls back to IP) |
| `mfa-challenge` | `POST /api/v1/core/auth/mfa/challenge` (public — no session exists yet at this point) | 5/minute | `challenge_token` + IP |

**Still open**: no other endpoint has a limiter beyond the generic `throttle:api` — read-heavy
browsing endpoints (master data, future Projects/ECommerce listing endpoints) and AI endpoints
still need traffic-shape-specific tuning once they exist. Don't assume a specific
requests-per-minute ceiling exists for anything not in the table above.

## Public, unauthenticated read endpoints

Besides health checks (below), a small set of read-only endpoints intentionally sit outside auth
because every client needs them before/without a session: `GET /api/v1/core/countries`,
`GET /api/v1/core/countries/{country}/cities`, `GET /api/v1/core/currencies` (master data for
registration-form pickers), and `GET /api/v1/core/settings` (site branding/contact/social info —
logo, name, contacts — for a mobile app or marketing site footer; admin-managed via the dashboard's
Settings screen, see `docs/architecture/admin-portal.md`).

## Health checks

Three, deliberately not versioned/module-namespaced since they're for infrastructure, not clients:

| Endpoint | Purpose | Auth |
|---|---|---|
| `GET /up` | Laravel's built-in liveness probe — process booted, nothing more. | none |
| `GET /api/v1/core/health` | Liveness, JSON. | none |
| `GET /api/v1/core/health/ready` | Readiness — checks DB, Redis, and cache actually respond; `200` with per-check results, or `503` if any fail. | none |

Use `/health/ready` (not `/up`) for anything that should fail an orchestrator's readiness gate when the database or Redis is unreachable — `/up` only proves the framework booted.

## API documentation (Scramble)

API reference docs are generated by `dedoc/scramble` primarily via **static analysis** — inferring
request/response shapes from FormRequest `rules()` and Resource `toArray()` bodies — rather than
hand-written annotations. There is no regeneration step: `/docs/openapi.json` is generated fresh
on every request. To keep `/docs` accurate for a new endpoint:

- Add a `#[Dedoc\Scramble\Attributes\Group('X / Y', weight: N)]` attribute on the controller class
  (once per controller, not per method) if it's a new group not already covered.
- Put the client-facing description + `@example` directly above each rule inside the FormRequest's
  `rules()` method as a `/** ... */` doc-comment — not a separate `@bodyParam` annotation.
- Only add a hand-written `#[Dedoc\Scramble\Attributes\Response(status, description:, examples:)]`
  when the response's meaning genuinely can't be inferred (a message added via a FormRequest
  `after()` closure, or a `ValidationException` thrown by a Service method — in the latter case,
  prefer a `@throws` PHPDoc tag on that Service method instead, which Scramble also reads).
- No auth annotation needed — `config('scramble.security_strategy')` derives it automatically from
  each route's `auth:sanctum` middleware.

Docs are served at:

| Path | Content |
|---|---|
| `/docs` | Interactive API documentation (Stoplight Elements) |
| `/docs/openapi.json` | OpenAPI 3.1 specification |

There is no Postman-collection endpoint — import `/docs/openapi.json` into Postman directly instead.

See `docs/decisions/0023-scramble-over-scribe.md` for why Scramble replaced Scribe (which itself
superseded l5-swagger, `docs/decisions/0003-scribe-over-l5swagger.md`).
