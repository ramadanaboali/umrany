@extends('admin.layouts.guest')

@section('title', __('admin.auth.sign_in'))

@section('content')
    <div class="text-center mt-2">
        <h5 class="text-primary">{{ __('admin.auth.welcome_back') }}</h5>
        <p class="text-muted">{{ __('admin.auth.sign_in_subtitle', ['app' => __('admin.common.app_name')]) }}</p>
    </div>
    <div class="p-2 mt-4">
        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('admin.auth.email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                    class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <div class="float-end">
                    <a href="{{ route('admin.password.request') }}" class="text-muted">{{ __('admin.auth.forgot_password') }}</a>
                </div>
                <label class="form-label" for="password">{{ __('admin.auth.password') }}</label>
                <div class="position-relative auth-pass-inputgroup">
                    <input type="password" id="password" name="password" required
                        class="form-control pe-5 password-input @error('password') is-invalid @enderror">
                    <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted shadow-none password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
                </div>
                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mt-4">
                <button class="btn btn-success w-100" type="submit">{{ __('admin.auth.sign_in') }}</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/velzon/js/pages/password-addon.init.js') }}"></script>
@endpush
