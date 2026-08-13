@extends('admin.layouts.app')

@section('title', 'New admin')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.admins.store') }}">
            @csrf

            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>

            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="{{ old('phone') }}">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>

            @if (Auth::guard('admin')->user()->is_super_admin)
                <label>
                    <input type="hidden" name="is_super_admin" value="0">
                    <input type="checkbox" name="is_super_admin" value="1" {{ old('is_super_admin') ? 'checked' : '' }}>
                    Grant Super Admin (unrestricted access, bypasses all permission checks)
                </label>
            @endif

            <fieldset>
                <legend>Roles</legend>
                <div class="checkbox-grid">
                    @foreach ($roles as $role)
                        <label>
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                {{ in_array($role->name, old('roles', [])) ? 'checked' : '' }}>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button type="submit" class="btn" style="margin-top:1rem;">Create admin</button>
        </form>
    </div>
@endsection
