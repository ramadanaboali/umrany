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
    <title>@yield('title') · {{ __('admin.common.app_name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @include('admin.partials.theme-mode-boot')
    @include('admin.partials.head-assets')
    @vite(['resources/css/admin.css', 'resources/js/admin-theme.js'])
</head>
<body>
    <div class="auth-page-wrapper pt-5">
        <div class="auth-one-bg-position auth-one-bg" id="auth-particles">
            <div class="bg-overlay"></div>
        </div>

        <div class="auth-page-content">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        @php
                            $siteSetting = \Modules\Core\Models\SiteSetting::current();
                            $brandLogoUrl = $siteSetting->logo_path
                                ? \Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->logo_path)
                                : null;
                        @endphp
                        <div class="text-center mt-sm-5 mb-4">
                            <a href="{{ route('admin.login') }}" class="d-inline-block auth-logo">
                                @if ($brandLogoUrl)
                                    <img src="{{ $brandLogoUrl }}" alt="{{ __('admin.common.app_name') }}" height="32">
                                @else
                                    <span class="fs-24 fw-bold text-white">{{ __('admin.common.app_name') }}</span>
                                @endif
                            </a>
                        </div>

                        <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
                            @include('admin.partials.language-switcher')
                            @include('admin.partials.theme-toggle', ['wrapperClass' => 'header-item'])
                        </div>

                        <div class="card mt-4">
                            <div class="card-body p-4">
                                @include('admin.partials.flash')
                                @yield('content')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.partials.footer')
    </div>

    @include('admin.partials.foot-scripts-libs')
    @stack('scripts')
</body>
</html>
