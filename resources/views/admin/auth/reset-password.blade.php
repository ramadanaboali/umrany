@extends('admin.layouts.guest')

@section('title', 'Reset password')

@section('content')
    <h1>Reset password</h1>
    <form method="POST" action="{{ route('admin.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus>
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <label for="password">New password</label>
        <input type="password" id="password" name="password" required>
        @error('password')<div class="error">{{ $message }}</div>@enderror

        <label for="password_confirmation">Confirm new password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required>

        <button type="submit">Reset password</button>
    </form>
@endsection
