{{-- Single source of truth for the LTR/RTL stylesheet swap — see App\Support\AdminTheme and
     docs/decisions/0022-admin-dashboard-en-ar-localization.md. `icons.min.css` ships no RTL
     variant (icon fonts are direction-agnostic), so it's unconditional. --}}
<link rel="shortcut icon" href="{{ asset('vendor/velzon/images/favicon.ico') }}">
<link href="{{ \App\Support\AdminTheme::css('bootstrap') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('vendor/velzon/css/icons.min.css') }}" rel="stylesheet" type="text/css">
<link href="{{ \App\Support\AdminTheme::css('app') }}" rel="stylesheet" type="text/css">
<link href="{{ \App\Support\AdminTheme::css('custom') }}" rel="stylesheet" type="text/css">
