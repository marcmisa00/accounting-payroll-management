
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Secure Verification | Accounting Portal</title>
    @vite(['resources/css/verify.css'])
    <script>
        // Same saved theme as the portal layout (no switch on this page)
        (function () {
            try {
                const stored = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if ((stored || (prefersDark ? 'dark' : 'light')) === 'dark') {
                    document.documentElement.classList.add('dark-mode');
                }
            } catch (e) {}
        })();
    </script>
    </head>

    @php
        // Validation errors arrive as $errors; a wrong or unregistered code arrives as session('error')
        $codeError = $errors->first('code') ?: session('error');
    @endphp

    <body>

<main class="verify-box" id="verifyBox">

    <div class="brand">
        <span class="brand-mark">
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M12 3 20 7v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
        </span>
        Accounting Portal
    </div>

    <div class="security-icon">
        <svg viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <rect x="5" y="10" width="14" height="11" rx="2.5"/>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            <path d="M12 14v3"/>
            <circle cx="12" cy="14" r="1"/>
        </svg>
    </div>

    <h1>Verify it's you</h1>

    <p class="subtitle">
        One last step to securely access your
        Accounting Portal. Enter the 6-digit code
        from your Google Authenticator app.
    </p>

    @if ($has2fa)
        <div class="status registered">
            <span class="status-dot"></span>
            Google Authenticator is registered
        </div>
    @else
        <div class="status unregistered">
            <span class="status-dot"></span>
            Google Authenticator is not registered
        </div>
    @endif

    <form method="POST"
          action="{{ route('accounting.verify.code') }}"
          id="verifyForm">

        @csrf

        <label class="field-label" for="otp-0">
            Authentication code
        </label>

        <div class="otp-group {{ $codeError ? 'shake' : '' }}"
             id="otpGroup">

            @for ($i = 0; $i < 6; $i++)
                <input
                    type="text"
                    class="otp-input"
                    id="otp-{{ $i }}"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="1"
                    autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                    aria-label="Digit {{ $i + 1 }}"
                    {{ $i === 0 ? 'autofocus' : '' }}
                    required
                >
            @endfor

        </div>

        <input type="hidden" name="code" id="fullCode">

        @if ($codeError)
            <div class="error-message" role="alert" id="codeError">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 8v5M12 16h.01"/>
                </svg>
                <span>{{ $codeError }}</span>
            </div>
        @endif

        <p class="hint" id="otpHint" aria-live="polite">
            Enter all 6 digits to continue
        </p>

        <button type="submit" class="verify-button" id="verifyButton">
            <svg id="buttonIcon" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <rect x="5" y="10" width="14" height="11" rx="2"/>
                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            </svg>
            <span id="buttonText">Verify &amp; Continue</span>
        </button>

    </form>

    <div class="secure-note">
        <svg viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <path d="M12 3 20 7v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/>
            <path d="m9 12 2 2 4-4"/>
        </svg>
        Protected by two-factor authentication
    </div>

    <p class="switch-link">
        Not you? <a href="{{ route('login') }}">Back to sign in</a>
    </p>

</main>

<footer class="page-footer">
    &copy; 2026 Accounting Portal<span class="dot">&middot;</span>Internal use only
</footer>


<script>
    const form = document.getElementById('verifyForm');
    const box = document.getElementById('verifyBox');
    const group = document.getElementById('otpGroup');
    const inputs = Array.from(document.querySelectorAll('.otp-input'));
    const fullCode = document.getElementById('fullCode');
    const hint = document.getElementById('otpHint');
    const button = document.getElementById('verifyButton');
    const buttonText = document.getElementById('buttonText');
    const buttonIcon = document.getElementById('buttonIcon');

    let submitting = false;
    let hasError = {{ $codeError ? 'true' : 'false' }};

    function getCode() {
        return inputs.map(input => input.value).join('');
    }

    function updateCode() {
        const code = getCode();

        fullCode.value = code;

        inputs.forEach(input => {
            input.classList.toggle('filled', input.value !== '');
        });

        // Preserve the error message until the user starts retrying.
        if (hasError) {
            hint.textContent = 'Please try again.';
            hint.dataset.state = 'error';
            return;
        }

        if (code.length === 6) {
            hint.textContent = 'All 6 digits entered. Ready to verify.';
            hint.dataset.state = 'ready';
        } else {
            hint.textContent = 'Enter all 6 digits to continue';
            hint.dataset.state = '';
        }
    }

    function clearError() {
        if (!hasError) return;

        hasError = false;
        group.classList.remove('invalid', 'shake');

        // Clear the server error message from the current page.
        const errorMessage = document.getElementById('codeError');
        if (errorMessage) {
            errorMessage.remove();
        }

        hint.textContent = 'Enter all 6 digits to continue';
        hint.dataset.state = '';
    }

    function shakeBoxes() {
        group.classList.remove('shake');

        // Restart the CSS animation.
        void group.offsetWidth;

        group.classList.add('shake');
    }

    inputs.forEach((input, index) => {
        input.addEventListener('input', () => {
            clearError();

            // Accept numeric characters only.
            input.value = input.value.replace(/\D/g, '').slice(-1);

            if (input.value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }

            updateCode();
        });

        input.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !input.value && index > 0) {
                inputs[index - 1].focus();
            }

            if (event.key === 'ArrowLeft' && index > 0) {
                inputs[index - 1].focus();
            }

            if (event.key === 'ArrowRight' && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });

        input.addEventListener('focus', () => {
            input.select();
        });

        input.addEventListener('paste', event => {
            event.preventDefault();

            clearError();

            const pasted = (event.clipboardData || window.clipboardData)
                .getData('text')
                .replace(/\D/g, '')
                .slice(0, 6);

            if (!pasted) return;

            inputs.forEach((field, i) => {
                field.value = pasted[i] || '';
            });

            const nextEmpty = inputs.findIndex(field => !field.value);

            if (nextEmpty === -1) {
                inputs[5].focus();
            } else {
                inputs[nextEmpty].focus();
            }

            updateCode();
        });
    });

    // Laravel returned an incorrect-code validation error.
    @if ($codeError)
        group.classList.add('invalid');

        inputs.forEach(input => {
            input.value = '';
        });

        updateCode();
        shakeBoxes();

        inputs[0].focus();
    @endif

    form.addEventListener('submit', event => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        const code = getCode();

        if (code.length !== 6) {
            event.preventDefault();

            const firstEmpty = inputs.findIndex(input => !input.value);

            if (firstEmpty !== -1) {
                inputs[firstEmpty].focus();
            }

            shakeBoxes();
            return;
        }

        fullCode.value = code;
        submitting = true;

        button.disabled = true;
        buttonText.textContent = 'Verifying securely...';

        buttonIcon.outerHTML =
            '<span class="spinner" id="buttonIcon" aria-hidden="true"></span>';

        // Dissolve while Laravel verifies the code.
        box.classList.add('dissolve');
    });
</script>
</body>
</html>
