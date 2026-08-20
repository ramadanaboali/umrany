# 21. Velzon Material as the admin dashboard theme

## Status

Accepted

## Context

`docs/architecture/admin-portal.md` documented the admin dashboard's layout as an explicit
placeholder — plain inline `<style>` CSS, light-only, no `dir` attribute, no i18n — pending a real
theme. The user provided a purchased theme, **Velzon Material** (a Bootstrap 5 static HTML/CSS/JS
export, Themesbrand, ~180 demo pages under `assets/`), with the requirement of a vertical sidebar
layout, a dark/light toggle, and (see ADR 0022) full English/Arabic localization with RTL.

The export is a raw static delivery: pre-compiled, minified CSS/JS with no Sass/build source, no
Laravel/React/Vue integration package, and ~128 MB total (mostly third-party JS libraries and demo
images this app has no use for). It also ships some markup/JS that is actively wrong once served
from a Laravel app rather than as flat `.html` files on a static host.

## Decision

- **Vendor a curated subset (~4.8 MB), not the full export**, into `public/vendor/velzon/`:
  compiled `app{,-rtl}.min.css`/`bootstrap{,-rtl}.min.css`/`custom{,-rtl}.min.css`/`icons.min.css`,
  `.woff2`/`.woff` font files only (the theme's own `@font-face` rules already list those formats
  first in the browser-support fallback chain — `.eot`/`.ttf`/`.svg` are never requested by a
  modern browser), `app.js`, and the four small JS libraries actually used (Bootstrap's bundle JS,
  SimpleBar, node-waves, Feather Icons). Treated as a pristine vendor drop — never hand-edited;
  project-specific CSS lives in `resources/css/admin.css` instead. Updating the theme later means
  overwriting this directory wholesale, not diffing hand-edits back in.
- **Not run through Vite.** The theme ships pre-compiled Bootstrap CSS/JS with no build source —
  there is nothing for a bundler to do, and mixing 340 KB of minified third-party CSS into the
  Tailwind v4 pipeline that `resources/css/app.css` (used by `welcome.blade.php`) already runs
  through would invite preflight conflicts for no benefit. Referenced via plain `asset()` calls.
  The admin panel's own small JS (`resources/js/admin-theme.js`, the existing
  `resources/js/admin.js`) and one override stylesheet (`resources/css/admin.css`) still go
  through Vite as before.
- **`assets/js/layout.js` is deliberately not shipped.** Reproduced directly: it snapshots every
  `<html>` attribute into `sessionStorage`, and on a mismatch against the previous snapshot runs
  `sessionStorage.clear(); location.reload();`. Since the RTL/locale work (ADR 0022) changes
  `dir`/`lang`/`data-layout-direction` server-side on every language switch, this would silently
  wipe the dark-mode preference (also stored via that same mechanism) on every switch. Replaced
  with an ~8-line inline pre-paint script (`resources/views/admin/partials/theme-mode-boot.blade.php`)
  that only sets `data-layout-mode` and mirrors it into `sessionStorage` for `app.js`'s own reads.
- **`assets/js/plugins.js` and the Lord Icon CDN loader are not shipped.** `plugins.js`
  `document.write`s a jsDelivr `<script>` tag plus three *relative* `assets/libs/...` paths that
  resolve incorrectly under any non-root path (e.g. `/admin/admins` → `/admin/assets/libs/...`,
  a 404) — and its "only if a plugin element exists on the page" guard is not actually
  conditional (it OR's three `querySelectorAll()` calls, which are always truthy `NodeList`
  objects). No current screen uses the plugins it loads (Toastify, Choices.js, Flatpickr).
- **Velzon's full "Layout Customizer" offcanvas panel is not ported.** Only the one requested
  dark/light toggle button and a language switcher are kept. The customizer's other eight axes
  (topbar/sidebar color, sidebar size, layout width/position/style, and — critically — a
  `horizontal`/`two-column`/`semibox` layout-type switch that would let a user override "vertical
  layout specifically") are unused surface area this task doesn't need (project-wide default:
  don't build abstractions the current task doesn't need). Omitting it also disarms `app.js`'s
  first-load auto-open of that panel (`document.querySelector('.btn[data-bs-target="#theme-
  settings-offcanvas"]')?.click()`), which would otherwise greet every first-time visitor with an
  open settings drawer advertising a "Buy Now" affiliate link.
- **Active sidebar-link state is computed server-side** (`request()->routeIs(...)` in
  `resources/views/admin/partials/sidebar.blade.php`), not via Velzon's own JS. That JS matches the
  current page against `.html` filenames (`location.pathname` → last path segment →
  `[href="<segment>"]`), which is meaningless once URLs are Laravel routes rather than static
  files, and fails silently rather than erroring.
- **Dark/light mode persists via `localStorage`, per browser, not Velzon's own `sessionStorage`.**
  `sessionStorage` would lose the setting the moment a tab closes, which reads as broken for no
  cost saved. `resources/js/admin-theme.js` uses a `MutationObserver` on `data-layout-mode` (not a
  second click handler) to persist whatever value ends up there, regardless of whether Velzon's
  own `app.js` or the guest layout's guarded fallback handler produced it — this stays
  order-independent and avoids a double-toggle bug. **Update:** also persisted per-account (DB) —
  see below, this changed after the original decision.
- **Every existing admin Blade view's markup was updated**, not just the two layout files. The
  architecture doc's original assumption ("swapping in a real theme only touches those two layout
  files") did not hold: bare `<table>`/`<label>`/`<input>` elements, `.checkbox-grid`,
  `.badge-active`, `.status`/`.errors`, and hardcoded inline `style="..."` attributes (all
  hardcoded to light-mode colors) needed real Bootstrap 5 classes to render correctly at all, let
  alone respect dark mode.

## Update: dark/light mode also persists per-account, and a real app.js crash was found and fixed

Two changes made after this ADR was first written:

1. **Dark/light mode now also persists to `admins.theme_mode` (DB), not just `localStorage`.**
   The original "per-browser only" decision above was a deliberate call at the time, but the user
   later asked for it explicitly — same DB-plus-browser pattern `preferred_language` already
   uses (ADR 0022), except there's no server-side rendering equivalent to `dir`/the RTL stylesheet
   swap for dark mode: the `<html data-layout-mode="...">` attribute is only ever set client-side
   (by the pre-paint inline script or a click), never by Blade. So the DB value is embedded as a
   *hint* (`App\Support\AdminTheme::savedMode()`, read by `partials/theme-mode-boot.blade.php`)
   that the pre-paint script prefers over `localStorage` when present — not a source of truth the
   server enforces the way it does for `dir`. `resources/js/admin-theme.js`'s existing
   `MutationObserver` now also fires a fire-and-forget `POST /admin/theme`
   (`App\Http\Controllers\Admin\ThemeController`) on every change, guarded by the same
   `#admin-id` meta-tag check `resources/js/admin.js` already uses to detect "is anyone signed
   in" — a no-op on the guest/login layout. This endpoint sits outside both the `guest:admin` and
   `auth:admin` route groups (same placement as `/admin/locale`), and needs no `can:` permission.

2. **A real bug: the dark/light toggle didn't work at all, because `app.js` crashed during page
   init before it ever reached the code that binds the toggle's click handler.** Reproduced
   directly with a headless-browser script (Playwright) before fixing, not assumed — the
   uncaught error was `Cannot set properties of null (setting 'innerHTML')`, then (after the
   first fix) `Cannot read properties of null (reading 'addEventListener')` twice more. Root
   cause: `app.js`'s layout-init code (`k("vertical")`, called unconditionally on every page load
   per the current `data-layout`) and its sidebar-hover/notification-modal setup reference three
   DOM elements **with no null-guard**, all three of which this integration had trimmed as
   "unused demo markup" when the theme was first wired in:
   - `#two-column-menu` (an always-empty placeholder `<div>` in the sidebar, needed even though
     the two-column layout variant itself is never used)
   - `#vertical-hover` (the sidebar's actual "hover a collapsed sidebar to preview it expanded"
     button — a real, cheap feature that had been trimmed along with the brand-block markup it
     used to sit next to)
   - `#removeNotificationModal` (an inert placeholder `<div>` — the notifications dropdown/modal
     feature itself still isn't shipped, only the element app.js unconditionally attaches a
     listener to)

   All three are restored as either the real feature (`#vertical-hover`) or an empty/inert
   placeholder element (`#two-column-menu`, `#removeNotificationModal`) in
   `resources/views/admin/partials/{sidebar,topbar}.blade.php`. **Lesson for any future trim of
   this vendored `app.js`:** it is not written defensively — removing an element it references
   anywhere in its single top-level IIFE can silently abort *all* setup code that runs after that
   point in the same function, including code with no relation to the removed element. Verify any
   future markup trim against a real browser console, not just visual inspection — the toggle
   button was still visible and clickable throughout; it simply had no listener attached.

## Consequences

- `docs/architecture/admin-portal.md`'s theme section needed a rewrite — the "only touches two
  files" claim is now documented as historically incorrect (see the update note appended to
  `docs/decisions/0007-in-monolith-blade-admin.md`), replaced with an honest accounting of what a
  future theme swap and a future new screen each actually touch.
- `Illuminate\Pagination\Paginator::useBootstrapFive()` is called in `AppServiceProvider::boot()`
  — the framework default pagination view is Tailwind-flavored and would render unstyled on
  `admins/index`/`users/index` without it.
- The Google Fonts `@import` inside the vendored `app.min.css` (Poppins/Inter — neither has Arabic
  glyphs) is left as-is; a system Arabic-capable font stack is layered on top for `[dir="rtl"]` in
  `resources/css/admin.css` rather than adding a second remote font dependency.
- No brand-color customization was attempted — this export ships no Sass source, so a future
  rebrand would mean hand-editing the vendored `custom.min.css`/`custom-rtl.min.css` (the theme's
  intended override point) or sourcing a build-capable delivery of the same purchase.
