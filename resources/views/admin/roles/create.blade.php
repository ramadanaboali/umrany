@extends('admin.layouts.app')

@section('title', __('admin.roles.create_title'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.roles.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('admin.roles.role_name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        placeholder="{{ __('admin.roles.role_name_placeholder') }}"
                        class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                @foreach ($permissionGroups as $group => $permissions)
                    <div class="card border mt-3">
                        <div class="card-header py-2">
                            <h6 class="mb-0 text-capitalize">{{ $group }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                @foreach ($permissions as $permission)
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                                id="perm-{{ $permission->id }}"
                                                {{ in_array($permission->name, old('permissions', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="perm-{{ $permission->id }}">{{ $permission->name }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary mt-3">{{ __('admin.roles.create') }}</button>
            </form>
        </div>
    </div>
@endsection
