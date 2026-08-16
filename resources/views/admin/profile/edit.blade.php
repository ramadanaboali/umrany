@extends('admin.layouts.app')

@section('title', 'My Profile')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.profile.update') }}">
            @csrf
            @method('PUT')

            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $admin->name) }}" required>

            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="{{ old('phone', $admin->phone) }}">

            <label for="email">Email (read-only)</label>
            <input type="text" id="email" value="{{ $admin->email }}" disabled>

            <fieldset>
                <legend>Change password (optional)</legend>
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                @error('current_password')<div class="error">{{ $message }}</div>@enderror

                <label for="password">New password</label>
                <input type="password" id="password" name="password" autocomplete="new-password">
                @error('password')<div class="error">{{ $message }}</div>@enderror

                <label for="password_confirmation">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
            </fieldset>

            <button type="submit" class="btn" style="margin-top:1rem;">Save</button>
        </form>
    </div>
@endsection
