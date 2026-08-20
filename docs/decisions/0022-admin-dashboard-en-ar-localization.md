# 22. Admin dashboard English/Arabic localization with RTL

## Status

Accepted

## Context

The admin dashboard had zero i18n: no `lang/` directory anywhere in the app, `<html lang="en">`
hardcoded, no `dir` attribute. The user asked for full English/Arabic admin support with a working
RTL layout, and confirmed (in conversation) that an admin's chosen language should persist on
their account, not just for the current session.

`Modules\Core\Enums\Language` already existed for exactly this purpose on the end-user side —
`Arabic`/`English` cases, a `direction(): 'rtl'|'ltr'` method, already backing
`user_profiles.preferred_language` and `PUT /api/v1/core/profile/language`.

## Decision

- **Reused `Modules\Core\Enums\Language` rather than introducing a new "locale" concept.** The new
  `admins.preferred_language` column is named and shaped identically to
  `user_profiles.preferred_language` (`string(2)`, default `'en'`, cast to `Language`) — one enum,
  one `direction()` helper, shared by both the API and the admin dashboard.
- **Translations live in root `lang/{en,ar}/`, never `resources/lang/`.** Laravel resolves the
  application's entire translation root to `resources/lang` the moment that directory exists
  (`Illuminate\Foundation\Application`'s path binding checks `is_dir(resource_path('lang'))`
  before falling back to `base_path('lang')`) — creating it, even for one file, would silently
  relocate every other translation lookup in the app. Neither directory existed before this
  change; `lang/en/admin.php` / `lang/ar/admin.php` hold the admin dashboard's own UI strings
  (nested by screen area), and `composer require --dev laravel-lang/common` +
  `php artisan lang:add en ar` generated complete, correct Arabic framework strings
  (`validation.php`, `auth.php`, `passwords.php`, `pagination.php`) rather than hand-translating
  roughly 120 validation messages. Field labels (`name`, `email`, `phone`, `password`, `status`,
  and the added `role(s)`/`permission(s)`/`search`) live in `validation.php`'s `attributes` block
  so one definition serves both `<label>` text and every validation message's `:attribute`
  placeholder — the existing admin FormRequests needed no changes to pick this up.
- **`Modules/Core/lang/{en,ar}/` covers messages Core's own services/rules throw that render inside
  the now-translated admin screens** — `AdminManagementService`, `AdminAuthService`,
  `RoleManagementService`'s `ValidationException` messages, and the two custom rules
  (`SaudiOrEgyptianPhoneNumber`, `NotAPreviousPassword`) shared with the end-user API. These are
  registered under an explicit `core::` namespace via a `CoreServiceProvider::registerTranslations()`
  override — nwidart's default implementation calls `loadTranslationsFrom($path)` with **no**
  namespace when `resources/lang/modules/core/` doesn't exist (which, per the rule above, it never
  will here), merging module lang files into the *global* namespace and colliding with the admin
  dashboard's own `lang/en/admin.php`. The end-user API side stays English-only regardless, since
  the new locale middleware only wraps `routes/admin.php`.
- **New `admins.preferred_language` column, resolved by a dedicated `SetAdminLocale` middleware**
  wrapping the *entire* `routes/admin.php` group in `bootstrap/app.php` (not just the
  authenticated half) — resolution order is the authenticated admin's DB column (so it follows
  them across devices) → a pre-login session value → a hardcoded `Language::English` default.
  Deliberately **not** falling back to `config('app.locale')` as a third tier: reproduced directly
  in this middleware's own regression test before writing this note, not assumed —
  `Illuminate\Foundation\Application::setLocale()` (called at the end of this same middleware)
  overwrites `config('app.locale')` as a side effect, so using it as a "default" creates a feedback
  loop where one request's language choice leaks into the next request's fallback the moment two
  requests share a container (true of this app's own test suite, and potentially true of an Octane
  worker if its sandboxed config ever inherits a mutated value). A hardcoded default has nothing to
  leak from.
- **Language switching is a dedicated `POST admin/locale` route**, placed outside *both* the
  `guest:admin` and `auth:admin` groups, not a field on the profile form. It must work
  pre-authentication (an Arabic-speaking admin needs an Arabic login page before they can sign in
  at all), and — like `profile.edit`/`profile.update` — it's a self-service preference with no
  `can:` permission gate. The guest case only writes to the session; the authenticated case also
  persists to the admin's own record via a new `AdminManagementService::updateLocale()`, kept
  separate from `updateOwnProfile()` so a language switch never runs that method's password-history
  side effect.
- **RTL activation is a full stylesheet swap**, not a CSS overlay — this Velzon export ships
  complete `*-rtl.min.css` files but wires none of them up. A single helper,
  `App\Support\AdminTheme`, computes the active `Language`/direction from `app()->getLocale()` and
  is the one place both layouts' `<html dir="...">` attribute and
  `resources/views/admin/partials/head-assets.blade.php`'s stylesheet `<link>`s read from.
- Velzon's own client-side language switching (`data-lang` attributes,
  `assets/lang/*.json` fetched and DOM-rewritten by `app.js`) is **not used** — it would run in
  parallel with, and fight, Laravel's server-rendered `__()`. The topbar language dropdown markup
  was adapted into real `<form method="POST">` submits to `admin.locale.update` instead. Those
  JSON files remain useful only as a translation reference, not as shipped code.

## Update: guest persistence moved from the session to a durable cookie, plus login-time adoption

Two gaps surfaced after this ADR was first written, both traced to the same root cause: a
language picked on the login page was easy to lose.

- **The guest-side value is now a long-lived `admin_locale` cookie (`LocaleController`, via
  `Cookie::queue()`), not `$request->session()->put(...)`.** A session is fragile across exactly
  the boundary that matters here — `AuthController::store()` calls `$request->session()->
  regenerate()` on login, and a fresh session after logout starts empty either way — so a
  same-session assumption silently broke the moment an admin actually signed in or came back
  later. `SetAdminLocale`'s guest fallback reads the cookie instead of `session()->get(...)`.
- **`AuthController::store()` now adopts the cookie value into the DB on successful login** (only
  when it differs from the admin's current `preferred_language`) — without this, the DB-wins-
  when-authenticated priority in `SetAdminLocale` would silently revert an admin to their old
  saved preference (or English) the instant they signed in, discarding the language they just
  explicitly picked on the login page moments before. Reproduced directly with a regression test
  before fixing, not assumed.
- The guest layout was also missing the dark/light toggle button entirely (only the language
  switcher was included) — added, using the same `partials/theme-toggle.blade.php` partial with
  a plain wrapper (the topbar's own wrapper classes hide it below the `sm` breakpoint, which is
  wrong for a login page a phone visitor might use).

## Consequences

- `Auth::guard('admin')->user()->preferred_language` is set only via
  `AdminRepositoryInterface::forceUpdate()` (never through `#[Fillable]`/mass assignment), matching
  this module's documented convention for every other admin-only column (`status`,
  `is_super_admin`).
- `docs/architecture/admin-portal.md` gained a "Localization (EN/AR, RTL)" section documenting the
  column, the middleware's registration point, and the deliberate lack of a `config('app.locale')`
  fallback tier.
- `AdminResetPasswordNotification` is still sent in the app's default locale regardless of the
  target admin's `preferred_language` — a one-line `->locale(...)` fix is cheap but out of scope
  for this change; noted here rather than left silently unaddressed.
- Every existing admin Pest test continues to pass unmodified except `AdminListingTest`, which
  asserted a hardcoded English string now sourced from a translation key — updated to assert
  against the key's rendered value instead of literal text.
