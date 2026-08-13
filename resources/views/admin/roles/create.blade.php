@extends('admin.layouts.app')

@section('title', 'New role')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf

            <label for="name">Role name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Finance">

            @foreach ($permissionGroups as $group => $permissions)
                <fieldset>
                    <legend>{{ $group }}</legend>
                    <div class="checkbox-grid">
                        @foreach ($permissions as $permission)
                            <label>
                                <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                    {{ in_array($permission->name, old('permissions', [])) ? 'checked' : '' }}>
                                {{ $permission->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <button type="submit" class="btn" style="margin-top:1rem;">Create role</button>
        </form>
    </div>
@endsection
