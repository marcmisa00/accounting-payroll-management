<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Accounting Portal')</title>
@vite(['resources/css/app.css'])
<script>
        // Apply the saved theme before the page paints. Dim and Midnight build on Dark,
        // Warm and Ocean on Light (resources/css/themes.css).
        (function () {
            try {
                const darkThemes = ['dark', 'dim', 'midnight'];
                const known = darkThemes.concat(['light', 'warm', 'ocean']);
                const stored = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = known.includes(stored) ? stored : (prefersDark ? 'dark' : 'light');
                const html = document.documentElement;
                html.classList.toggle('dark-mode', darkThemes.includes(theme));
                if (theme !== 'light' && theme !== 'dark') {
                    html.classList.add('theme-' + theme);
                }
            } catch (e) {}
        })();
    </script>
    @yield('styles')
    
</head>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<body class="{{ request()->routeIs('payroll.*','holidays.*', 'attendance.*', 'leaves.*', 'hr.*','hso.*','transfer.*','attendance.*','payroll.history*') ? 'payroll-page sidebar-collapsed' : '' }} {{ request()->routeIs('payroll.show', 'payroll.edit.show') ? 'payroll-show' : '' }}">
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

                <li>
                    <a href="{{ route('payroll.benefits.index') }}"
                    class="{{ request()->routeIs('payroll.benefits.index', 'payroll.benefits.edit') ? 'active' : '' }}">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                        <span class="nav-text">Salary &amp; Benefits</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('payroll.benefits.history') }}"
                    class="{{ request()->routeIs('payroll.benefits.history') ? 'active' : '' }}">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span class="nav-text">Benefits History</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('payroll.items.index') }}"
                    class="{{ request()->routeIs('payroll.items.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-list-check"></i>
                        <span class="nav-text">Addons &amp; Deductions</span>
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

                <li>
                    <a href="{{ route('hr.movement-tracker') }}"
                    class="{{ request()->routeIs('hr.movement-tracker') ? 'active' : '' }}">
                        <i class="fa-solid fa-right-left"></i>
                        <span class="nav-text">Movement Tracker</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('hr.work-setup-tracker') }}"
                    class="{{ request()->routeIs('hr.work-setup-tracker') ? 'active' : '' }}">
                        <i class="fa-solid fa-house-laptop"></i>
                        <span class="nav-text">Work Setup Tracker</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('hr.resigned') }}"
                    class="{{ request()->routeIs('hr.resigned') ? 'active' : '' }}">
                        <i class="fa-solid fa-user-xmark"></i>
                        <span class="nav-text">Resigned Employees</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('hr.infractions') }}"
                    class="{{ request()->routeIs('hr.infractions*') ? 'active' : '' }}">
                        <i class="fa-solid fa-folder-open"></i>
                        <span class="nav-text">Employee Infractions</span>
                    </a>
                </li>

            </ul>

        </li>

        
        <a href="{{ route('hso.index') }}"
           class="{{ request()->routeIs('hso.*') ? 'active' : '' }}">
            <span class="icon">
                <i class="fa-solid fa-laptop-house"></i>
            </span>
            <span class="nav-text">HSO Application</span>
        </a>

        <a href="{{ route('transfer.index') }}"
           class="{{ request()->routeIs('transfer.*') ? 'active' : '' }}">
            <span class="icon">
                <i class="fa-solid fa-building-circle-arrow-right"></i>
            </span>
            <span class="nav-text">Site Transfer</span>
        </a>

      

        <a href="{{ route('attendance.index') }}"
           class="{{ request()->routeIs('attendance.*') ? 'active' : '' }}">
            <span class="icon">
                <i class="fa-solid fa-user-clock"></i>
            </span>
            <span class="nav-text">Attendance</span>
        </a>

        <a href="{{route('holidays.index')}}" class="{{ request()->routeIs('holidays.*') ? 'active' : '' }}">
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

        <div class="theme-picker">
            <button type="button"
                    class="theme-toggle"
                    id="themeToggle"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    aria-controls="themeMenu"
                    title="Change theme">
                <span class="theme-swatch" id="themeCurrentSwatch" data-theme="light" aria-hidden="true"></span>
                <span class="theme-name" id="themeCurrentName">Light</span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>

            <div class="theme-menu" id="themeMenu" role="menu" aria-label="Theme" hidden>
                <div class="theme-menu-label">Theme</div>
                @foreach (['light' => ['Light', ''], 'dark' => ['Dark', ''], 'dim' => ['Dim', ''], 'midnight' => ['Midnight', ''], 'warm' => ['Warm', ''], 'ocean' => ['Ocean', '']] as $themeKey => [$themeName, $themeHint])
                    <button type="button" class="theme-option" role="menuitemradio" aria-checked="false" data-theme="{{ $themeKey }}">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        <span class="theme-swatch" data-theme="{{ $themeKey }}" aria-hidden="true"></span>
                        <span>{{ $themeName }}</span>
                        <small>{{ $themeHint }}</small>
                    </button>
                @endforeach
            </div>
        </div>

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
            const html = document.documentElement; // theme classes live on <html>
            const toggleBtn = document.getElementById('themeToggle');
            const menu = document.getElementById('themeMenu');
            const options = Array.from(menu.querySelectorAll('.theme-option'));
            const currentSwatch = document.getElementById('themeCurrentSwatch');
            const currentName = document.getElementById('themeCurrentName');

            // Dim and Midnight build on Dark, Warm and Ocean on Light (resources/css/themes.css)
            const THEMES = {
                light: { name: 'Light', dark: false },
                dark: { name: 'Dark', dark: true },
                dim: { name: 'Dim', dark: true },
                midnight: { name: 'Midnight', dark: true },
                warm: { name: 'Warm', dark: false },
                ocean: { name: 'Ocean', dark: false },
            };

            function savedTheme() {
                try {
                    const stored = localStorage.getItem('theme');
                    return THEMES[stored] ? stored : null;
                } catch (e) {
                    return null;
                }
            }

            // 1. Initial theme: saved choice > system preference
            function getInitialTheme() {
                return savedTheme() || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            }

            // 2. Apply a theme and update the button and menu
            function applyTheme(theme) {
                html.classList.toggle('dark-mode', THEMES[theme].dark);
                Object.keys(THEMES).forEach(function (key) {
                    html.classList.toggle('theme-' + key, key === theme && key !== 'light' && key !== 'dark');
                });

                currentSwatch.dataset.theme = theme;
                currentName.textContent = THEMES[theme].name;
                toggleBtn.setAttribute('aria-label', 'Theme: ' + THEMES[theme].name + '. Change theme');
                options.forEach(function (option) {
                    option.setAttribute('aria-checked', String(option.dataset.theme === theme));
                });
            }

            applyTheme(getInitialTheme());

            // 3. Open / close the menu
            function openMenu() {
                menu.hidden = false;
                toggleBtn.setAttribute('aria-expanded', 'true');
                (options.find(function (o) { return o.getAttribute('aria-checked') === 'true'; }) || options[0]).focus();
            }

            function closeMenu(focusButton) {
                menu.hidden = true;
                toggleBtn.setAttribute('aria-expanded', 'false');
                if (focusButton) toggleBtn.focus();
            }

            toggleBtn.addEventListener('click', function () {
                menu.hidden ? openMenu() : closeMenu(false);
            });

            document.addEventListener('click', function (e) {
                if (!menu.hidden && !e.target.closest('.theme-picker')) closeMenu(false);
            });

            menu.addEventListener('keydown', function (e) {
                const i = options.indexOf(document.activeElement);
                if (e.key === 'Escape') { e.preventDefault(); closeMenu(true); }
                if (e.key === 'ArrowDown') { e.preventDefault(); options[(i + 1) % options.length].focus(); }
                if (e.key === 'ArrowUp') { e.preventDefault(); options[(i - 1 + options.length) % options.length].focus(); }
                if (e.key === 'Tab') closeMenu(false);
            });

            // 4. Pick a theme, revealing it as a wave from the button
            options.forEach(function (option) {
                option.addEventListener('click', function () {
                    const newTheme = option.dataset.theme;
                    closeMenu(true);

                    if (currentSwatch.dataset.theme === newTheme) return;

                    const commit = function () {
                        applyTheme(newTheme);
                        try { localStorage.setItem('theme', newTheme); } catch (e) {}
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
            });

            // 5. Follow the system light/dark setting until a theme is picked
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                if (!savedTheme()) {
                    applyTheme(e.matches ? 'dark' : 'light');
                }
            });
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const menus = document.querySelectorAll('.payroll-menu');
            const canHover = window.matchMedia('(hover: hover)').matches;
            const isCollapsed = function () { return document.body.classList.contains('sidebar-collapsed'); };

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

                // If we are already inside one of this menu's pages,
                // keep its submenu open.
                if (toggle.classList.contains('active')) {
                    submenu.classList.add('show');
                    if (arrow) arrow.classList.add('rotate');
                }

                // Click the menu to open/close manually
                toggle.addEventListener('click', function (e) {

                    e.preventDefault();

                    if (isCollapsed()) {
                        // With a mouse the flyout is already open from hovering, so a click keeps it open
                        const open = canHover || !menu.classList.contains('flyout-open');
                        menu.classList.toggle('flyout-open', open);
                        toggle.setAttribute('aria-expanded', String(open));
                        closeFlyouts(menu);
                        return;
                    }

                    submenu.classList.toggle('show');
                    if (arrow) arrow.classList.toggle('rotate');

                });

                // Collapsed sidebar with a mouse: hovering opens the flyout; a short
                // delay lets the pointer cross the gap to it before it closes
                if (canHover) {
                    let closeTimer;

                    menu.addEventListener('mouseenter', function () {
                        if (!isCollapsed()) return;
                        clearTimeout(closeTimer);
                        closeFlyouts(menu);
                        menu.classList.add('flyout-open');
                        toggle.setAttribute('aria-expanded', 'true');
                    });

                    menu.addEventListener('mouseleave', function () {
                        if (!isCollapsed()) return;
                        closeTimer = setTimeout(function () {
                            menu.classList.remove('flyout-open');
                            toggle.setAttribute('aria-expanded', 'false');
                        }, 250);
                    });
                }
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