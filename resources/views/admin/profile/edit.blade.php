@extends('admin.layouts.app')

@section('title', __('admin.profile.title'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.profile.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('admin.profile.name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $admin->name) }}" required
                        class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">{{ __('admin.profile.phone') }}</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $admin->phone) }}"
                        class="form-control @error('phone') is-invalid @enderror">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('admin.profile.email_readonly') }}</label>
                    <input type="text" id="email" value="{{ $admin->email }}" disabled class="form-control">
                </div>

                <div class="card border mt-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0">{{ __('admin.profile.change_password') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">{{ __('admin.profile.current_password') }}</label>
                            <div class="position-relative auth-pass-inputgroup">
                                <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                                    class="form-control pe-5 password-input @error('current_password') is-invalid @enderror">
                                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted shadow-none password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
                            </div>
                            @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('admin.profile.new_password') }}</label>
                            <div class="position-relative auth-pass-inputgroup">
                                <input type="password" id="password" name="password" autocomplete="new-password"
                                    class="form-control pe-5 password-input @error('password') is-invalid @enderror">
                                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted shadow-none password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
                            </div>
                            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label for="password_confirmation" class="form-label">{{ __('admin.profile.confirm_new_password') }}</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">{{ __('admin.common.save') }}</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/velzon/js/pages/password-addon.init.js') }}"></script>
@endpush
