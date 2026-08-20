{{-- The one file a new admin screen touches to add a nav entry — see
     docs/architecture/admin-portal.md § Theme and assets. Active state is computed server-side
     via request()->routeIs(): Velzon's own active-link JS matches on the current page's .html
     filename, which is meaningless for Laravel routes (see docs/decisions/0021-velzon-material-
     admin-theme.md), so it is not loaded. --}}
<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        @include('admin.partials.brand')
        {{-- app.js's layout-init (function z()) unconditionally does
             document.getElementById("vertical-hover").addEventListener(...) with no null-guard —
             omitting this button entirely throws and aborts the rest of app.js's setup, same
             failure mode as the #two-column-menu div above. It's also a real, cheap feature:
             toggles "hover an icon-only collapsed sidebar to preview it expanded." --}}
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">
            {{-- Empty on purpose — app.js's layout-init code (k("vertical"), run unconditionally
                 on load per data-layout) unconditionally does
                 document.getElementById("two-column-menu").innerHTML="", with no null-guard, for
                 every layout including "vertical". Omitting this element entirely throws
                 "Cannot set properties of null" and silently aborts the rest of app.js's setup —
                 including the dark/light toggle's click handler, several menu-lines below. Kept
                 even though the two-column layout variant itself isn't used. Verified directly
                 with a headless-browser repro before adding this back, not assumed. --}}
            <div id="two-column-menu"></div>
            <ul class="navbar-nav" id="navbar-nav">
                <li class="menu-title"><span>{{ __('admin.nav.menu') }}</span></li>

                <li class="nav-item">
                    <a href="{{ route('admin.dashboard') }}" class="nav-link menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="bx bx-home-circle"></i> <span>{{ __('admin.nav.dashboard') }}</span>
                    </a>
                </li>

                @can('admins.list')
                    <li class="nav-item">
                        <a href="{{ route('admin.admins.index') }}" class="nav-link menu-link {{ request()->routeIs('admin.admins.*') ? 'active' : '' }}">
                            <i class="bx bx-user-circle"></i> <span>{{ __('admin.nav.admins') }}</span>
                        </a>
                    </li>
                @endcan

                @can('users.list')
                    <li class="nav-item">
                        <a href="{{ route('admin.users.index') }}" class="nav-link menu-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                            <i class="bx bx-group"></i> <span>{{ __('admin.nav.users') }}</span>
                        </a>
                    </li>
                @endcan

                @can('roles.list')
                    <li class="nav-item">
                        <a href="{{ route('admin.roles.index') }}" class="nav-link menu-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                            <i class="bx bx-shield-quarter"></i> <span>{{ __('admin.nav.roles') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.permissions.index') }}" class="nav-link menu-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}">
                            <i class="bx bx-key"></i> <span>{{ __('admin.nav.permissions') }}</span>
                        </a>
                    </li>
                @endcan

                @can('settings.view')
                    <li class="nav-item">
                        <a href="{{ route('admin.settings.edit') }}" class="nav-link menu-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                            <i class="bx bx-cog"></i> <span>{{ __('admin.nav.settings') }}</span>
                        </a>
                    </li>
                @endcan

                <li class="nav-item">
                    <a href="{{ route('admin.profile.edit') }}" class="nav-link menu-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}">
                        <i class="bx bx-user"></i> <span>{{ __('admin.nav.profile') }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-background"></div>
</div>
<div class="vertical-overlay"></div>
