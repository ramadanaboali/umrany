{{-- Shared by sidebar.blade.php and topbar.blade.php — both need the same dual `.logo-dark`/
     `.logo-light` block (Velzon's own CSS shows only one depending on data-layout-mode; the
     class names describe the LOGO's own color, not the mode it's shown in). Renders the admin's
     uploaded Modules\Core\Models\SiteSetting logo if one exists, otherwise falls back to the
     localized brand name as text rather than Velzon's own placeholder artwork. --}}
@php
    $siteSetting = \Modules\Core\Models\SiteSetting::current();
    $brandLogoUrl = $siteSetting->logo_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->logo_path)
        : null;
    $brandName = __('admin.common.app_name');
@endphp
<a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
    @if ($brandLogoUrl)
        <span class="logo-sm"><img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" height="22"></span>
        <span class="logo-lg"><img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" height="28"></span>
    @else
        <span class="logo-sm fs-16 fw-bold text-body">{{ \Illuminate\Support\Str::substr($brandName, 0, 1) }}</span>
        <span class="logo-lg fs-18 fw-bold text-body">{{ $brandName }}</span>
    @endif
</a>
<a href="{{ route('admin.dashboard') }}" class="logo logo-light">
    @if ($brandLogoUrl)
        <span class="logo-sm"><img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" height="22"></span>
        <span class="logo-lg"><img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" height="28"></span>
    @else
        <span class="logo-sm fs-16 fw-bold text-white">{{ \Illuminate\Support\Str::substr($brandName, 0, 1) }}</span>
        <span class="logo-lg fs-18 fw-bold text-white">{{ $brandName }}</span>
    @endif
</a>
