# 11. Admin forgot-password reveals account existence

## Status

Accepted

## Context

The end-user API's forgot-password endpoint (`POST /api/v1/core/auth/forgot-password`) is
deliberately enumeration-safe: it always returns the same generic "If that account exists, a reset
code has been sent" message whether or not the submitted email/mobile matches a real account. That
posture makes sense for a public-signup surface with an unknown, potentially large population of
end users — leaking which emails are registered is a real privacy/abuse risk there.

The admin dashboard's forgot-password screen (`app/Http/Controllers/Admin/PasswordResetController`)
is a different threat model entirely: admin accounts are never self-registered, the population is
small and known to the operator, and every admin route already sits behind its own login wall and
rate limiting. Reusing the end-user endpoint's generic-message posture here bought no real privacy
benefit but did cost usability — an admin who mistypes their email gets no signal that they need to
correct it, they just silently never receive a reset link.

## Decision

- `app/Http/Requests/Admin/ForgotPasswordRequest` validates `email` with
  `Rule::exists('admins', 'email')->whereNull('deleted_at')` and a specific message: "No admin
  account exists with that email address." An unknown (or soft-deleted) admin email now fails
  validation loudly instead of silently doing nothing.
- `PasswordResetController::storeForgot()` branches on the password broker's actual return status
  (`Password::RESET_LINK_SENT` / `Password::RESET_THROTTLED` / default) rather than always showing
  one generic message.
- The end-user API's forgot-password endpoint is explicitly **not** touched — it keeps its
  enumeration-safe generic response. This is a scoped, intentional asymmetry between the two
  surfaces, not an oversight.
- `Modules\Core\Models\Admin::sendPasswordResetNotification()` overrides the base
  `CanResetPassword` behavior to send `Modules\Core\Notifications\AdminResetPasswordNotification`
  instead of Laravel's stock `Illuminate\Auth\Notifications\ResetPassword`. The stock notification
  hardcodes its mail link to `route('password.reset', ...)` — a route name this app never
  registers, since `routes/admin.php` names its equivalent `admin.password.reset` (the `admin.`
  group prefix). Sending the stock notification unmodified threw a `RouteNotFoundException` the
  moment it actually rendered, which `Notification::fake()`-based tests never exercise (`fake()`
  records dispatch without calling `toMail()`) — this shipped initially and was only caught by
  manually exercising the real flow. `AdminPasswordResetTest::
  test_reset_link_points_at_the_real_admin_reset_route()` renders the mail message directly as a
  standing regression check.

## Consequences

- An attacker who can reach `/admin/forgot-password` can enumerate which emails have admin
  accounts. Accepted because: no public admin self-signup, small known population, the route
  already sits behind the admin dashboard's own access controls and rate limiting, and the
  usability cost of staying generic here was real while the enumeration risk is low.
- If the admin dashboard is ever exposed to a wider or less-trusted audience, revisit this — the
  end-user endpoint's enumeration-safe pattern is the template to fall back to.
