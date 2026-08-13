@extends('admin.layouts.app')

@section('title', 'Edit role')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')

            <label for="name">Role name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $role->name) }}" required>

            @php $currentPermissions = old('permissions', $role->permissions->pluck('name')->all()); @endphp

            @foreach ($permissionGroups as $group => $permissions)
                <fieldset>
                    <legend>{{ $group }}</legend>
                    <div class="checkbox-grid">
                        @foreach ($permissions as $permission)
                            <label>
                                <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                    {{ in_array($permission->name, $currentPermissions) ? 'checked' : '' }}>
                                {{ $permission->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <button type="submit" class="btn" style="margin-top:1rem;">Save</button>
        </form>
    </div>
@endsection
