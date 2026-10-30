@extends('layouts.auth')

@section('title', 'Register')
@section('heading', 'Create account')
@section('subtitle', 'Available to employees with Accounting access in HRIS.')

@section('content')
    <form method="POST" action="{{ route('register.submit') }}" class="auth-form">
        @csrf

        <label class="field-label" for="idno">ID number</label>
        <input type="text" id="idno" name="idno" class="text-input"
               value="{{ old('idno') }}" maxlength="20"
               autocomplete="off" required autofocus>

        <label class="field-label" for="username">Username</label>
        <input type="text" id="username" name="username" class="text-input"
               value="{{ old('username') }}" minlength="4" maxlength="30"
               autocomplete="username" required>
        <p class="hint">4–30 characters: letters, numbers, dot, dash or underscore</p>

        <label class="field-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="text-input"
               minlength="8" autocomplete="new-password" required>

        <label class="field-label" for="password_confirmation">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="text-input"
               minlength="8" autocomplete="new-password" required>
        <p class="hint">At least 8 characters</p>

        <button type="submit" class="verify-button" data-busy="Checking access...">
            <span>Continue</span>
        </button>
    </form>

    <p class="switch-link">
        Already have an account? <a href="{{ route('login') }}">Sign in</a>
    </p>
@endsection
