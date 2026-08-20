{{-- Velzon's own dark/light button. Persistence is handled by resources/js/admin-theme.js via a
     MutationObserver on data-layout-mode — see docs/decisions/0021-velzon-material-admin-theme.md
     for why (localStorage over Velzon's own sessionStorage, and an observer instead of a second
     click handler to avoid double-toggling with assets/js/app.js's own handler).

     $wrapperClass defaults to the topbar's own spacing/visibility classes; the guest layout
     passes a plain wrapper instead so the button doesn't disappear below the `sm` breakpoint —
     a login-page visitor on a phone should still be able to toggle dark mode. --}}
<div class="{{ $wrapperClass ?? 'ms-1 header-item d-none d-sm-flex' }}">
    <button type="button"
        class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle light-dark-mode shadow-none"
        title="{{ __('admin.theme.toggle_mode') }}">
        <i class="bx bx-moon fs-22"></i>
    </button>
</div>
