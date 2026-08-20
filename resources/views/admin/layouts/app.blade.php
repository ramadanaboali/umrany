<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ \App\Support\AdminTheme::direction() }}"
      data-layout="vertical"
      data-layout-direction="{{ \App\Support\AdminTheme::direction() }}"
      data-topbar="light"
      data-sidebar="dark"
      data-sidebar-size="lg"
      data-sidebar-image="none"
      data-preloader="disable">
<head>
    <meta charset="utf-8">
    <title>@yield('title', __('admin.nav.dashboard')) · {{ __('admin.common.app_name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth('admin')
        <meta name="admin-id" content="{{ Auth::guard('admin')->id() }}">
    @endauth

    @include('admin.partials.theme-mode-boot')
    @include('admin.partials.head-assets')
    @vite(['resources/css/admin.css', 'resources/js/admin-theme.js', 'resources/js/admin.js'])
</head>
<body>
    <div id="layout-wrapper">
        @include('admin.partials.topbar')
        @include('admin.partials.sidebar')

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">
                    <x-admin.page-header :title="trim($__env->yieldContent('title'))" />

                    @include('admin.partials.flash')

                    @yield('content')
                </div>
            </div>

            @include('admin.partials.footer')
        </div>
    </div>

    @include('admin.partials.foot-scripts')
    @stack('scripts')
</body>
</html>
