{{-- Trimmed from Velzon's index.html topbar: dropped the search widget, apps grid, and
     notifications dropdown (all demo-data placeholders with no backing feature yet — Rule 3,
     don't ship UI for a feature that doesn't exist). Kept: hamburger, brand, language switcher,
     dark/light toggle, user menu. See docs/decisions/0021-velzon-material-admin-theme.md. --}}
<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <div class="navbar-brand-box horizontal-logo">
                    @include('admin.partials.brand')
                </div>

                <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger shadow-none" id="topnav-hamburger-icon">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
            </div>

            <div class="d-flex align-items-center">
                @include('admin.partials.language-switcher')
                @include('admin.partials.theme-toggle')

                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn shadow-none" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <span class="avatar-xs rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center header-profile-user">
                                <i class="bx bx-user fs-18"></i>
                            </span>
                            <span class="text-start ms-xl-2">
                                <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ Auth::guard('admin')->user()->name }}</span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="{{ route('admin.profile.edit') }}">
                            <i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">{{ __('admin.nav.profile') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i>
                                <span class="align-middle">{{ __('admin.nav.logout') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- Inert placeholder — app.js's init unconditionally does
     document.getElementById("removeNotificationModal").addEventListener("show.bs.modal", ...)
     with no null-guard, same failure mode as #two-column-menu/#vertical-hover above. Nothing
     triggers it (the notifications dropdown itself isn't shipped, per the note at the top of
     this file), so it stays empty rather than restoring the dropdown/modal UI it belonged to. --}}
<div id="removeNotificationModal"></div>
