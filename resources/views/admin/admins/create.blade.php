@extends('admin.layouts.app')

@section('title', __('admin.admins.create_title'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.admins.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('admin.admins.name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('admin.admins.email') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                        class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">{{ __('admin.admins.phone') }}</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                        class="form-control @error('phone') is-invalid @enderror">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('admin.admins.password') }}</label>
                    <input type="password" id="password" name="password" required
                        class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">{{ __('admin.admins.password_confirmation') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="form-control">
                </div>

                <div class="card border mt-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0">{{ __('admin.admins.roles') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            @foreach ($roles as $role)
                                <div class="col-sm-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}"
                                            id="role-{{ $role->id }}"
                                            {{ in_array($role->name, old('roles', [])) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">{{ __('admin.admins.create') }}</button>
            </form>
        </div>
    </div>
@endsection
