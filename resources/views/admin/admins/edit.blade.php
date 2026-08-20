@extends('admin.layouts.app')

@section('title', __('admin.admins.edit_title'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.admins.update', $admin) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('admin.admins.name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $admin->name) }}" required
                        class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">{{ __('admin.admins.phone') }}</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $admin->phone) }}"
                        class="form-control @error('phone') is-invalid @enderror">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('admin.admins.email_readonly') }}</label>
                    <input type="text" id="email" value="{{ $admin->email }}" disabled class="form-control">
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">{{ __('admin.admins.status') }}</label>
                    <select id="status" name="status" class="form-select"
                        {{ $admin->id === Auth::guard('admin')->id() ? 'disabled' : '' }}>
                        @foreach (\Modules\Core\Enums\AdminStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ old('status', $admin->status->value) === $status->value ? 'selected' : '' }}>
                                {{ ucfirst($status->value) }}
                            </option>
                        @endforeach
                    </select>
                    @if ($admin->id === Auth::guard('admin')->id())
                        <input type="hidden" name="status" value="{{ $admin->status->value }}">
                        <div class="form-text">{{ __('admin.admins.cannot_change_own_status') }}</div>
                    @endif
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
                                            {{ in_array($role->name, old('roles', $admin->roles->pluck('name')->all())) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">{{ __('admin.common.save') }}</button>
            </form>
        </div>
    </div>
@endsection
