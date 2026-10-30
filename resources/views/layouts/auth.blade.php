<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title') | Accounting Portal</title>
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
<body>

<main class="verify-box">

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

    <h1>@yield('heading')</h1>
    <p class="subtitle">@yield('subtitle')</p>

    @php
        // Field errors arrive as $errors; access and credential failures arrive as session('error')
        $authError = session('error') ?: $errors->first();
    @endphp

    @if ($authError)
        <div class="error-message" role="alert">
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 8v5M12 16h.01"/>
            </svg>
            <span>{{ $authError }}</span>
        </div>
    @endif

    @if (session('success'))
        <div class="success-message" role="status">{{ session('success') }}</div>
    @endif

    @yield('content')

</main>

<footer class="page-footer">
    &copy; 2026 Accounting Portal<span class="dot">&middot;</span>Internal use only
</footer>

<script>
    // Prevent double submits
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.querySelector('span').textContent = button.dataset.busy || 'Please wait...';
            }
        });
    });
</script>
</body>
</html>
