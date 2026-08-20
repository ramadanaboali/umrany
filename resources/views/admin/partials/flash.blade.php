{{-- Unifies the flash/validation-error/live-permissions-changed banner markup that used to be
     duplicated (and had drifted) between layouts/app.blade.php and layouts/guest.blade.php.
     #permissions-changed-banner's id and `hidden` attribute are a contract with
     resources/js/admin.js — do not rename or restructure without updating that listener too. --}}
@auth('admin')
    <div id="permissions-changed-banner" class="alert alert-warning alert-dismissible fade show" role="alert" hidden>
        {{ __('admin.flash.permissions_changed') }}
        <a href="{{ request()->fullUrl() }}">{{ __('admin.common.refresh_page') }}</a>.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endauth

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
