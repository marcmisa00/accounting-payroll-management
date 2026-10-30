@extends('layouts.auth')

@section('title', 'Set Up Authenticator')
@section('heading', 'Set up two-factor authentication')
@section('subtitle', 'Scan the QR code with Google Authenticator, then enter the 6-digit code it shows.')

@section('content')
    <div class="qr-box">
        {!! $qrSvg !!}
    </div>

    <p class="setup-label">Can't scan? Enter this key manually</p>
    <code class="setup-key">{{ $secret }}</code>

    <form method="POST" action="{{ route('register.setup.confirm') }}" class="auth-form">
        @csrf

        <label class="field-label" for="code">Authentication code</label>
        <input type="text" id="code" name="code" class="text-input code-input"
               inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
               autocomplete="one-time-code" required autofocus>

        <button type="submit" class="verify-button" data-busy="Verifying...">
            <span>Complete registration</span>
        </button>
    </form>

    <p class="switch-link">
        Registering as {{ $username }} &middot; <a href="{{ route('register') }}">Start over</a>
    </p>
@endsection
