@extends('layouts.auth')

@section('title', 'Sign In')
@section('heading', 'Sign in')
@section('subtitle', 'Enter your username and password to continue.')

@section('content')
    @php
        $username = old('username', $rememberedUsername);
    @endphp

    <form method="POST" action="{{ route('login.submit') }}" class="auth-form">
        @csrf

        <label class="field-label" for="username">Username</label>
        <input type="text" id="username" name="username" class="text-input"
               value="{{ $username }}" maxlength="30"
               autocomplete="username" required {{ $username ? '' : 'autofocus' }}>

        <label class="field-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="text-input"
               autocomplete="current-password" required {{ $username ? 'autofocus' : '' }}>

        <label class="check-row">
            <input type="checkbox" name="remember" value="1"
                   {{ old('remember', $rememberedUsername ? '1' : '') ? 'checked' : '' }}>
            Remember me
        </label>

        <button type="submit" class="verify-button" data-busy="Signing in...">
            <span>Sign in</span>
        </button>
    </form>

    <p class="switch-link">
        Don't have an account? <a href="{{ route('register') }}">Register</a>
    </p>
@endsection
