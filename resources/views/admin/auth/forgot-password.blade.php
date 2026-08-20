@extends('admin.layouts.guest')

@section('title', __('admin.auth.forgot_title'))

@section('content')
    <div class="text-center mt-2">
        <h5 class="text-primary">{{ __('admin.auth.forgot_title') }}</h5>
        <p class="text-muted">{{ __('admin.auth.forgot_intro') }}</p>
    </div>
    <div class="p-2 mt-4">
        <form method="POST" action="{{ route('admin.password.email') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('admin.auth.email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                    class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mt-4">
                <button class="btn btn-success w-100" type="submit">{{ __('admin.auth.send_reset_link') }}</button>
            </div>
        </form>
    </div>
    <div class="mt-4 text-center">
        <a href="{{ route('admin.login') }}" class="text-muted">{{ __('admin.auth.back_to_sign_in') }}</a>
    </div>
@endsection
