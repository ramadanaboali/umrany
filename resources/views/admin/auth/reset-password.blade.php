@extends('admin.layouts.guest')

@section('title', __('admin.auth.reset_title'))

@section('content')
    <div class="text-center mt-2">
        <h5 class="text-primary">{{ __('admin.auth.reset_title') }}</h5>
    </div>
    <div class="p-2 mt-4">
        <form method="POST" action="{{ route('admin.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('admin.auth.email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus
                    class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">{{ __('admin.auth.new_password') }}</label>
                <div class="position-relative auth-pass-inputgroup">
                    <input type="password" id="password" name="password" required
                        class="form-control pe-5 password-input @error('password') is-invalid @enderror">
                    <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted shadow-none password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
                </div>
                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">{{ __('admin.auth.confirm_new_password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required class="form-control">
            </div>

            <div class="mt-4">
                <button class="btn btn-success w-100" type="submit">{{ __('admin.auth.reset_password') }}</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/velzon/js/pages/password-addon.init.js') }}"></script>
@endpush
