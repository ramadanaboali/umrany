@extends('admin.layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h1>Umrany Admin</h1>
    <form method="POST" action="{{ route('admin.login.store') }}">
        @csrf
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        @error('password')<div class="error">{{ $message }}</div>@enderror

        <button type="submit">Sign in</button>
    </form>
    <div class="links">
        <a href="{{ route('admin.password.request') }}">Forgot your password?</a>
    </div>
@endsection
