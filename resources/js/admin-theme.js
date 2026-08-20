/**
 * Dark/light mode persistence — both localStorage (per-browser, so it survives before an admin
 * is even signed in) and, when signed in, the account itself via POST /admin/theme (so the
 * choice follows the admin across browsers/devices) — see
 * docs/decisions/0021-velzon-material-admin-theme.md.
 *
 * A MutationObserver (not a second click handler) persists whatever value ends up on
 * data-layout-mode, regardless of what set it — this stays order-independent against Velzon's
 * own assets/js/app.js click handler (loaded on the authenticated layout) and avoids a
 * double-toggle bug. The guarded click handler below only fires on the guest/login layout, where
 * app.js isn't loaded at all and nothing else would flip the attribute.
 */
const root = document.documentElement;

new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        if (mutation.attributeName === 'data-layout-mode') {
            const mode = root.getAttribute('data-layout-mode') || 'light';
            localStorage.setItem('umrany.admin.layout-mode', mode);
            syncModeToAccount(mode);
        }
    }
}).observe(root, { attributes: true, attributeFilter: ['data-layout-mode'] });

document.addEventListener('click', (event) => {
    const button = event.target.closest('.light-dark-mode');

    // #page-topbar only exists on the authenticated layout, where assets/js/app.js is loaded and
    // already has its own click handler for this button — only act here on the guest/login
    // layout, which has no topbar and doesn't load app.js at all.
    if (button && !document.getElementById('page-topbar')) {
        const next = root.getAttribute('data-layout-mode') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-layout-mode', next);
    }
});

/**
 * Fire-and-forget — the toggle itself is already instant/client-side; a slow or failed request
 * here must never block or roll back the visual switch the admin already saw happen. A no-op on
 * the guest/login layout, which has no #admin-id meta tag (no admin to persist to yet).
 */
function syncModeToAccount(mode) {
    const adminId = document.querySelector('meta[name="admin-id"]')?.getAttribute('content');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!adminId || !csrfToken) {
        return;
    }

    fetch('/admin/theme', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
        },
        body: JSON.stringify({ mode }),
        keepalive: true,
    }).catch(() => {});
}
