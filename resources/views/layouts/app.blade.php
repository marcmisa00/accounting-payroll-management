<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Accounting Portal')</title>
@vite(['resources/css/app.css'])
<script>
        (function () {
            try {
                const stored = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = stored || (prefersDark ? 'dark' : 'light');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark-mode');
                }
            } catch (e) {}
        })();
    </script>
    @yield('styles')
    
</head>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<body class="{{ request()->routeIs('payroll.*','holidays.*', 'attendance.*', 'leaves.*', 'hr.*') ? 'payroll-page sidebar-collapsed' : '' }} {{ request()->routeIs('payroll.show', 'payroll.edit.show') ? 'payroll-show' : '' }}">
    <aside class="sidebar">

            <button type="button" class="sidebar-toggle" id="sidebarToggle">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            
    <div class="brand">
        <span class="brand-text">Accounting Portal</span>
    </div>

    <nav>

        <a href="{{ route('dashboard') }}"
           class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="icon">
                <i class="fa-solid fa-house"></i>
            </span>
            <span class="nav-text">Dashboard</span>
        </a>


        {{-- PAYROLL --}}
      <li class="nav-item payroll-menu">

            <a href="javascript:void(0);"
            class="payroll-toggle {{ request()->routeIs('payroll.*') ? 'active' : '' }}">

                <span class="icon">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </span>

                <span class="nav-text">Payroll</span>

                <i class="fa-solid fa-chevron-down payroll-arrow"></i>
            </a>

            <ul class="payroll-submenu" data-title="Payroll">

                <li>
                    <a href="{{ route('payroll.create') }}"
                    class="{{ request()->routeIs('payroll.create') ? 'active' : '' }}">
                        <i class="fa-solid fa-plus"></i>
                        <span class="nav-text">Create Payroll</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('payroll.manage') }}"
                    class="{{ request()->routeIs('payroll.manage') ? 'active' : '' }}">
                        <i class="fa-solid fa-list"></i>
                        <span class="nav-text">Manage Payroll</span>
                    </a>
                </li>

            </ul>

        </li>

        {{-- LEAVES & BENEFITS (same sub-menu markup/classes as Payroll) --}}
        <li class="nav-item payroll-menu">

            <a href="javascript:void(0);"
            class="payroll-toggle {{ request()->routeIs('leaves.*') ? 'active' : '' }}">

                <span class="icon">
                    <i class="fa-solid fa-umbrella-beach"></i>
                </span>

                <span class="nav-text">Leaves &amp; Benefits</span>

                <i class="fa-solid fa-chevron-down payroll-arrow"></i>
            </a>

            <ul class="payroll-submenu" data-title="Leaves &amp; Benefits">

                <li>
                    <a href="{{ route('leaves.applications') }}"
                    class="{{ request()->routeIs('leaves.applications') ? 'active' : '' }}">
                        <i class="fa-solid fa-file-signature"></i>
                        <span class="nav-text">Leave Application</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('leaves.time-adjustments') }}"
                    class="{{ request()->routeIs('leaves.time-adjustments') ? 'active' : '' }}">
                        <i class="fa-solid fa-user-clock"></i>
                        <span class="nav-text">Time Adjustment</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('leaves.overtime') }}"
                    class="{{ request()->routeIs('leaves.overtime') ? 'active' : '' }}">
                        <i class="fa-solid fa-business-time"></i>
                        <span class="nav-text">Overtime</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('leaves.eeo') }}"
                    class="{{ request()->routeIs('leaves.eeo') ? 'active' : '' }}">
                        <i class="fa-solid fa-person-walking-arrow-right"></i>
                        <span class="nav-text">EEO Application</span>
                    </a>
                </li>

            </ul>

        </li>

        {{-- EMPLOYEE DETAILS (same sub-menu markup/classes as Payroll) --}}
        <li class="nav-item payroll-menu">

            <a href="javascript:void(0);"
            class="payroll-toggle {{ request()->routeIs('hr.*') ? 'active' : '' }}">

                <span class="icon">
                    <i class="fa-solid fa-users"></i>
                </span>

                <span class="nav-text">Employee Details</span>

                <i class="fa-solid fa-chevron-down payroll-arrow"></i>
            </a>

            <ul class="payroll-submenu" data-title="Employee Details">

                <li>
                    <a href="{{ route('hr.roster') }}"
                    class="{{ request()->routeIs('hr.roster*') ? 'active' : '' }}">
                        <i class="fa-solid fa-id-badge"></i>
                        <span class="nav-text">HR Roster</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('hr.leave-credits') }}"
                    class="{{ request()->routeIs('hr.leave-credits*') ? 'active' : '' }}">
                        <i class="fa-solid fa-scale-balanced"></i>
                        <span class="nav-text">Leave Credits</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('hr.monthly-leave-credits') }}"
                    class="{{ request()->routeIs('hr.monthly-leave-credits') ? 'active' : '' }}">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span class="nav-text">Monthly Leave Credits</span>
                    </a>
                </li>

            </ul>

        </li>

        <a href="{{ route('attendance.index') }}"
           class="{{ request()->routeIs('attendance.*') ? 'active' : '' }}">
            <span class="icon">
                <i class="fa-solid fa-user-clock"></i>
            </span>
            <span class="nav-text">Attendance</span>
        </a>

        <a href="{{route('holidays.index')}}">
            <span class="icon">
                <i class="fa fa-calendar"></i>
            </span>
            <span class="nav-text">Calendar</span>
        </a>

        <a href="{{ route('payroll.history.index') }}"
            class="{{ request()->routeIs('payroll.history*') ? 'active' : '' }}">

            <span class="icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </span>
            <span class="nav-text">History</span>
        </a>

    </nav>


    <div class="logout">
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit">
                <i class="fa-solid fa-right-from-bracket"></i>
            <span class="logout-text">Log out</span>
            </button>
        </form>
    </div>

</aside>

    <div class="main">
       <header class="topbar">

    <h1>@yield('page-title', '')</h1>

    <div class="user">

            @php
                $portalIdno = session('portal_idno');
                $portalDisplayName = $portalIdno
                    ? trim((string) \Illuminate\Support\Facades\DB::connection('hris')
                        ->table('employee_profile')
                        ->where('idno', $portalIdno)
                        ->value('firstname'))
                    : '';
                if ($portalDisplayName === '') {
                    $portalDisplayName = 'there';
                }
                $portalFirstName = ucfirst(explode(' ', $portalDisplayName)[0]);
                $hour = now()->setTimezone(config('app.timezone', 'Asia/Manila'))->hour;
                $greeting = $hour < 12
                    ? 'Good morning'
                    : ($hour < 18 ? 'Good afternoon' : 'Good evening');
            @endphp

        <button type="button"
                class="theme-toggle"
                id="themeToggle"
                role="switch"
                aria-checked="false"
                aria-label="Switch to dark mode"
                title="Toggle dark mode">
            <span class="switch-knob"></span>
        </button>

        <span>{{ $greeting }}, {{ $portalFirstName }}!</span>

        <div class="avatar" aria-hidden="true">
            {{ strtoupper(substr($portalFirstName, 0, 1)) }}
        </div>

    </div>

</header>

        <div class="content">
            
            @yield('content')
        </div>
    </div>

    <script>
        (function() {
            const html = document.documentElement; // use <html> for theme class
            const toggleBtn = document.getElementById('themeToggle');

            // 1. Determine initial theme: localStorage > system preference > light
            function getInitialTheme() {
                const stored = localStorage.getItem('theme');
                if (stored === 'dark') return 'dark';
                if (stored === 'light') return 'light';
                // no stored preference → use system
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            // 2. Apply theme and update the light-switch state
            function applyTheme(theme) {
                const isDark = theme === 'dark';

                html.classList.toggle('dark-mode', isDark);
                toggleBtn.setAttribute('aria-checked', String(isDark));
                toggleBtn.setAttribute(
                    'aria-label',
                    isDark ? 'Switch to light mode' : 'Switch to dark mode'
                );
                toggleBtn.setAttribute(
                    'title',
                    isDark ? 'Dark mode is on — switch to light mode' : 'Dark mode is off — switch to dark mode'
                );
            }

            // 3. Set initial theme
            const initialTheme = getInitialTheme();
            applyTheme(initialTheme);

            // 4. Toggle on button click, revealing the new theme as a wave from the switch
            toggleBtn.addEventListener('click', function() {
                const isDark = html.classList.contains('dark-mode');
                const newTheme = isDark ? 'light' : 'dark';

                const commit = function () {
                    applyTheme(newTheme);
                    localStorage.setItem('theme', newTheme);
                };

                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (!document.startViewTransition || reduceMotion) {
                    commit();
                    return;
                }

                const rect = toggleBtn.getBoundingClientRect();
                const x = rect.left + rect.width / 2;
                const y = rect.top + rect.height / 2;
                const radius = Math.hypot(
                    Math.max(x, window.innerWidth - x),
                    Math.max(y, window.innerHeight - y)
                );

                html.classList.add('theme-wave');
                const transition = document.startViewTransition(commit);

                transition.ready.then(function () {
                    html.animate(
                        { clipPath: [
                            'circle(0px at ' + x + 'px ' + y + 'px)',
                            'circle(' + radius + 'px at ' + x + 'px ' + y + 'px)'
                        ] },
                        { duration: 700, easing: 'cubic-bezier(0.65, 0, 0.35, 1)', pseudoElement: '::view-transition-new(root)' }
                    );
                }).catch(function () {});

                transition.finished.finally(function () {
                    html.classList.remove('theme-wave');
                });
            });

            // 5. (Optional) Listen for system preference changes when no explicit choice
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                if (!localStorage.getItem('theme')) {
                    applyTheme(e.matches ? 'dark' : 'light');
                }
            });
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const menus = document.querySelectorAll('.payroll-menu');

            // Collapsed sidebar: sub-menus open as a flyout beside the icon
            function closeFlyouts(except) {
                menus.forEach(function (menu) {
                    if (menu === except) return;
                    menu.classList.remove('flyout-open');
                    const toggle = menu.querySelector('.payroll-toggle');
                    if (toggle) toggle.setAttribute('aria-expanded', 'false');
                });
            }

            // Every sidebar menu with a sub-menu (Payroll, Leaves & Benefits, ...)
            menus.forEach(function (menu) {

                const toggle = menu.querySelector('.payroll-toggle');
                const submenu = menu.querySelector('.payroll-submenu');
                const arrow = menu.querySelector('.payroll-arrow');

                if (!toggle || !submenu) {
                    return;
                }

                toggle.setAttribute('aria-haspopup', 'true');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.title = submenu.dataset.title || '';

                // If we are already inside one of this menu's pages,
                // keep its submenu open.
                if (toggle.classList.contains('active')) {
                    submenu.classList.add('show');
                    if (arrow) arrow.classList.add('rotate');
                }

                // Click the menu to open/close manually
                toggle.addEventListener('click', function (e) {

                    e.preventDefault();

                    if (document.body.classList.contains('sidebar-collapsed')) {
                        const open = menu.classList.toggle('flyout-open');
                        toggle.setAttribute('aria-expanded', String(open));
                        closeFlyouts(menu);
                        return;
                    }

                    submenu.classList.toggle('show');
                    if (arrow) arrow.classList.toggle('rotate');

                });
            });

            // Close a flyout by clicking elsewhere or pressing Escape
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.payroll-menu')) closeFlyouts();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                const open = document.querySelector('.payroll-menu.flyout-open .payroll-toggle');
                closeFlyouts();
                if (open) open.focus();
            });

            // Expanding/collapsing the sidebar closes any open flyout
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () { closeFlyouts(); });
            }

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const sidebarToggle = document.getElementById('sidebarToggle');

            if (!sidebarToggle) {
                return;
            }

            const isPayrollPage = document.body.classList.contains('payroll-page');

            if (isPayrollPage && !document.body.classList.contains('sidebar-collapsed')) {
                document.body.classList.add('sidebar-collapsed');
            }

            sidebarToggle.addEventListener('click', function () {
                document.body.classList.toggle('sidebar-collapsed');
            });

        });
    </script>
</body>
</html>