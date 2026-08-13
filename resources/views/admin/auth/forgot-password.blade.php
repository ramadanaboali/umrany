@extends('admin.layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h1>Forgot password</h1>
    <form method="POST" action="{{ route('admin.password.email') }}">
        @csrf
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <button type="submit">Send reset link</button>
    </form>
    <div class="links">
        <a href="{{ route('admin.login') }}">Back to sign in</a>
    </div>
@endsection
