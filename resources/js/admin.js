import './echo';

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
