import './echo';

/**
 * Icon-only table action buttons (see resources/views/admin/{admins,users,roles}/index.blade.php
 * and users/show.blade.php) carry their accessible label via `data-bs-toggle="tooltip"` instead
 * of visible text — Bootstrap's own JS (loaded earlier, in admin.partials.foot-scripts) never
 * auto-initializes tooltips on its own, they need this explicit pass. This module script is
 * deferred, so it already runs after that plain footer script has executed and `window.bootstrap`
 * exists; every table row is server-rendered on load, not injected later, so a one-time pass here
 * is enough — no MutationObserver needed.
 */
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new window.bootstrap.Tooltip(el));

/**
 * Listens for Modules\Core\Events\AdminPermissionsChanged on the current admin's private channel
 * and surfaces a manual-refresh banner — never a forced reload, so an in-progress form isn't
 * yanked away. See docs/decisions/0008-admin-rbac-live-refresh-via-reverb.md.
 */
const adminId = document.querySelector('meta[name="admin-id"]')?.getAttribute('content');

if (adminId) {
    window.Echo.private(`core.admin.${adminId}`).listen('.permissions.changed', () => {
        document.getElementById('permissions-changed-banner')?.removeAttribute('hidden');
    });
}
