# 29. Centralized notification dispatch, API locale detection, and universal list filtering

## Status

Accepted

## Context

Three related gaps, all about cross-cutting API behavior that had grown inconsistent instead of
centralized:

- Every one of the 8 `NotificationEvent` notification classes had its subject/greeting/body text
  hardcoded in English directly in the PHP class, dispatched via 8 separate
  `$notifiable->notify(new XNotification(...))` call sites scattered across `AuthService`,
  `Modules\Core\Services\Admin\UserManagementService`, and `VerificationCodeService` — no shared
  dispatch point, no localization, and no way for a caller to narrow which channels a specific send
  should use.
- The end-user API had no locale-detection mechanism at all — only the admin-guard side had one
  (`App\Http\Middleware\SetAdminLocale`, session/`web`-guard only, unaffected by this change).
- `spatie/laravel-query-builder` was already a required Composer package and already documented in
  `docs/api/conventions.md` as "the" filtering convention, but genuinely unused anywhere in real
  code. Of the 5 existing GET-collection endpoints, only `GET /me/notifications` paginated (via
  raw `Illuminate\Pagination`, hand-building a non-standard 3-key `meta` with no `links` at all —
  not matching what the same doc already claimed the shape was), and none supported any filter
  query param.

## Decision

### Notification dispatch and localization

- New `Modules\Core\Services\NotificationDispatchService::send(User $notifiable, NotificationEvent
  $event, array $data = [], ?array $channels = null, ?string $locale = null)` is now the single
  entry point for all 8 `NotificationEvent`-mapped notifications. It `match($event)`-builds the
  right typed notification instance from `$data` — each notification class keeps its own typed
  constructor (e.g. `NewDeviceLoginNotification` still takes `?string $deviceName, ?string $ip,
  Carbon $loggedInAt`, no magic array-hydration), now with an added `string $renderLocale`
  constructor property.
  - Named `$renderLocale`, not `$locale` — `Illuminate\Notifications\Notification` already
    declares its own public, untyped, non-readonly `$locale` property (used by the framework's
    own `Notification::locale()` fluent setter). A `private readonly string $locale` constructor
    promotion on a subclass collides with it (confirmed directly: Larastan flags
    `property.visibility`/`property.extraNativeType`/`property.readOnly` all at once). We
    deliberately did not adopt the framework's own locale mechanism (`HasLocalePreference` +
    `Notification::locale()`) instead — it depends on a callback-scoped global `app()->setLocale()`
    mutation inside the queue worker; capturing locale as an explicit constructor value at
    *dispatch* time (before queueing) is more deterministic and easier to reason about, at the
    cost of one more constructor parameter per class.
- **Locale resolution order**: explicit `$locale` param → the recipient's own stored
  `UserProfile::preferred_language` (via `NotificationDispatchService::resolveLocale()`) →
  `config('app.locale')`.
  - For the 5 self-triggered events where the acting request and the recipient are the same
    person (`RegistrationCompleted`, `VerificationCodeSent`, `PasswordChanged`,
    `PasswordResetCompleted`, `NewDeviceLogin`), the call site passes `locale: app()->getLocale()`
    explicitly — i.e. whatever `SetLocaleFromRequest` (below) already resolved for the current
    request, so "the language selected or sent" on that request is what the notification renders
    in.
  - For `SuspiciousLoginAttempt`, no explicit locale is passed — the account owner is the
    recipient, but the *triggering* request (the failed login attempt) may not be them at all, so
    their own stored preference is the right default, not the failed attempt's own headers.
  - For the two admin-initiated events (`AccountSuspended`, `AccountActivated`), no explicit locale
    is passed either — the acting admin is never the recipient, so only the recipient's own stored
    preference (or the app default) makes sense.
- **Channel override**: an explicit `$channels` array *narrows* (never widens/forces) what
  `BaseUserNotification::via()` would otherwise send — intersected with, not exempt from,
  `NotificationPreferenceService::allows()`'s per-user opt-out for non-mandatory events.
  `BaseUserNotification` gained `restrictChannelsTo(array $channels): static`, a fluent setter
  `via()` consults before returning. Deliberate: a caller can say "only email for this one," never
  "email this user even though they explicitly turned email off for this event type" — this is not
  a consent-bypass mechanism. `VerificationCodeNotification` overrides `via()` itself (it already
  picks mail-vs-SMS by delivery type, not preference) and does not consult this restriction at
  all — narrowing channels on a verification code doesn't make sense; the code must reach its
  destination.
- Localized content lives in `Modules/Core/lang/{en,ar}/notifications.php`, one key per
  `NotificationEvent` case, matching the module's existing `core::` lang-namespace convention.
  Every notification's `toMail()`/`toArray()` (and `VerificationCodeNotification`'s
  `toLoggedSms()`) reads through `__('core::notifications.<event>.<key>', [...substitutions],
  $this->renderLocale)` — the explicit 3rd-arg locale override on `__()`, never a global
  `app()->setLocale()` mutation inside the notification class itself.
- Not used for `MfaStateChangedNotification` (`MfaService`, 2 call sites) or
  `AdminResetPasswordNotification` (`Admin` model) — neither has a `NotificationEvent` case (the
  product's 8-event list is exactly the enum's 8 cases), so both keep their existing direct
  `->notify()` calls, unlocalized, out of scope here.

### API locale detection

- New `Modules\Core\Http\Middleware\SetLocaleFromRequest`, registered globally on the `api`
  middleware group in `bootstrap/app.php` — applies to every module's API routes automatically.
  Resolution order: a matching `Accept-Language` header → the authenticated user's own stored
  `UserProfile::preferred_language` → `config('app.locale')`.
- **Deliberately not** `$request->getPreferredLanguage(Language::values())` for the whole
  decision — that method falls back to the *first* element of the given locale list whenever
  nothing in the header matches, including when no header was sent at all (confirmed directly: it
  never returns null for a non-empty `$locales` array — `Request::create()`'s own test-request
  defaults exposed this, and it would misbehave identically for a real client that sends no
  header). Real quality-value parsing still comes from `Request::getLanguages()` (no hand-rolled
  Accept-Language parsing); only the "nothing matched" decision — fall through to the stored
  preference, not blindly pick `Language::values()[0]` — is ours to make.
- Deliberately separate from `App\Http\Middleware\SetAdminLocale` — different guard, different
  resolution order, not touched by this change.

### Universal list pagination and filtering

- `GET /auth/sessions` and `GET /me/notifications` are rebuilt on
  `Spatie\QueryBuilder\QueryBuilder::for(...)->allowedFilters(...)->paginate(...)`, returning a
  Resource collection directly (`SessionResource::collection($paginator)` /
  `NotificationResource::collection($paginator)`) instead of a hand-built array — this alone fixes
  the response-shape drift noted above, since Laravel's own `AnonymousResourceCollection`
  auto-produces the full `data`+`links`+`meta` shape when wrapping a `LengthAwarePaginator` this
  way. Notifications gained `filter[read]` (`AllowedFilter::callback(...)`, since `read_at` is a
  nullable timestamp, not a boolean column) and `filter[type]`; sessions gained
  `filter[device_name]`.
- `GET /countries`, `.../cities`, `GET /currencies` stay unpaginated (scoped directly with the
  user: these back picker/dropdown UIs where paging would hurt, not help) but gained
  `filter[search]` (partial match against `name_en`/`name_ar`). `is_active` stays a
  server-enforced constant on these public, unauthenticated endpoints, never a client-controllable
  filter.
- `Spatie\QueryBuilder\QueryBuilder::allowedFilters()` is genuinely variadic
  (`AllowedFilter|string ...$filters`) — passing a single array literal
  (`->allowedFilters([$f1, $f2])`) is not just a style nit but a real runtime bug (confirmed via
  Larastan's `argument.type` check, which caught it before it shipped): PHP does not spread an
  array into a variadic parameter automatically, so `$filters` would end up as a one-element array
  containing the array itself, and `AllowedFilter::partial($theArray)` would blow up at runtime.
- New standing **Rule 10** in root `CLAUDE.md` makes this the default for every future
  list/index endpoint, with the master-data picker pattern as the one named, deliberate exception.

## Consequences

- Every notification a user receives now renders in the language they (or, for admin-initiated
  events, they alone) actually prefer, without any endpoint having to think about locale itself —
  `SetLocaleFromRequest` and `NotificationDispatchService`'s resolution chain handle it once.
- A future notification event follows the same shape: add a `NotificationEvent` case, a lang-file
  entry, a `match()` branch in `NotificationDispatchService::build()`, and a constructor parameter
  for locale — not a new bespoke dispatch path.
- `GET /me/notifications`'s response shape changed (real `links`, full 7-key `meta` instead of the
  old 3-key one) — a breaking change for any existing client parsing the old shape, but there were
  no external API consumers yet at this stage of the product.
- Date/time text inside notification bodies (e.g. `NewDeviceLoginNotification`'s "Time: ..." line,
  via `Carbon::toDayDateTimeString()`) is not locale-aware — only the surrounding sentence text is
  localized. Carbon locale formatting was out of scope for this change; revisit if Arabic date
  formatting becomes a real product requirement.
