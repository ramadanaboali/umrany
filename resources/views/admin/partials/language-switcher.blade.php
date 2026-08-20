{{-- Adapted from Velzon's topbar language dropdown. Deliberately does NOT use Velzon's own
     client-side data-lang/assets/lang/*.json switching (see docs/decisions/0022-admin-dashboard-
     en-ar-localization.md) — each option is a real POST to admin.locale.update so the choice is
     persisted server-side via Modules\Core\Enums\Language. Available on both the authenticated
     and guest layouts, since an Arabic-speaking admin needs the login page in Arabic too. --}}
@php
    $currentLanguage = \App\Support\AdminTheme::language();
    // Keyed by the enum's string value, not the enum case itself — PHP array keys must be
    // int|string, an enum instance can't be used as an offset.
    $languages = [
        \Modules\Core\Enums\Language::English->value => ['case' => \Modules\Core\Enums\Language::English, 'flag' => 'us.svg', 'label' => __('admin.theme.english')],
        \Modules\Core\Enums\Language::Arabic->value => ['case' => \Modules\Core\Enums\Language::Arabic, 'flag' => 'sa.svg', 'label' => __('admin.theme.arabic')],
    ];
@endphp
<div class="dropdown ms-1 topbar-head-dropdown header-item">
    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle shadow-none"
        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <img src="{{ asset('vendor/velzon/images/flags/'.$languages[$currentLanguage->value]['flag']) }}"
            alt="{{ __('admin.theme.language') }}" height="20" class="rounded">
    </button>
    <div class="dropdown-menu dropdown-menu-end">
        @foreach ($languages as $value => $meta)
            <form method="POST" action="{{ route('admin.locale.update') }}" class="d-inline">
                @csrf
                <input type="hidden" name="language" value="{{ $value }}">
                <button type="submit" class="dropdown-item py-2 {{ $meta['case'] === $currentLanguage ? 'active' : '' }}">
                    <img src="{{ asset('vendor/velzon/images/flags/'.$meta['flag']) }}" alt=""
                        class="me-2 rounded" height="18">
                    <span class="align-middle">{{ $meta['label'] }}</span>
                </button>
            </form>
        @endforeach
    </div>
</div>
