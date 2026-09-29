<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Accounting Portal')</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* ----- light theme (default) ----- */
        :root {
            --bg-body: #f2f4f7;
            --text-primary: #222;
            --sidebar-bg-start: #4b79a1;
            --sidebar-bg-end: #283e51;
            --sidebar-text: rgba(255, 255, 255, 0.85);
            --sidebar-border: rgba(255, 255, 255, 0.15);
            --sidebar-hover: rgba(255, 255, 255, 0.08);
            --sidebar-active: rgba(255, 255, 255, 0.12);
            --topbar-bg: #ffffff;
            --topbar-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            --avatar-bg: #4b79a1;
            --card-bg: #ffffff;
        }

        /* ----- dark theme overrides ----- */
        html.dark-mode {
            --bg-body: #12181f;
            --text-primary: #e8edf2;
            --sidebar-bg-start: #1a2530;
            --sidebar-bg-end: #0d141c;
            --sidebar-text: rgba(255, 255, 255, 0.75);
            --sidebar-border: rgba(255, 255, 255, 0.08);
            --sidebar-hover: rgba(255, 255, 255, 0.06);
            --sidebar-active: rgba(255, 255, 255, 0.12);
            --topbar-bg: #1a232d;
            --topbar-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
            --avatar-bg: #2c3e4e;
            --card-bg: #1e2a36;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            margin: 0;
            background: var(--bg-body);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            transition: background 0.2s, color 0.2s;
        }

        /* ----- Sidebar ----- */
        .sidebar {
            width: 240px;
            flex-shrink: 0;
            position: relative;
            background: linear-gradient(
                180deg,
                var(--sidebar-bg-start),
                var(--sidebar-bg-end)
            );
            color: #fff;
            display: flex;
            flex-direction: column;
            padding: 28px 0;
            transition: width 0.25s ease, background 0.2s;
        }

                /* Sidebar collapse button */
        .sidebar-toggle {
            position: absolute;
            top: 50%;
            right: -14px;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 50%;
            background: var(--sidebar-bg-end);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
            transition: transform 0.25s ease, background 0.15s ease;
        }

        .sidebar-toggle:hover {
            background: var(--sidebar-bg-start);
        }

        /* Collapsed sidebar */
        body.sidebar-collapsed .sidebar {
            width: 0;
            overflow: visible;
            padding-left: 0;
            padding-right: 0;
        }

        /* Hide sidebar contents when collapsed */
        body.sidebar-collapsed .sidebar > *:not(.sidebar-toggle) {
            opacity: 0;
            visibility: hidden;
        }

        /* Move arrow when collapsed */
        body.sidebar-collapsed .sidebar-toggle {
            transform: translateY(-50%) rotate(180deg);
        }

        /* Main automatically expands because body is flex */
        body.sidebar-collapsed .main {
            flex: 1;
        }

        /* =========================================
        TOPBAR SIDEBAR BUTTON
        ========================================= */

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .topbar-sidebar-toggle {
            width: 38px;
            height: 38px;

            border: 1px solid rgba(128, 128, 128, 0.35);
            border-radius: 8px;

            background: transparent;
            color: var(--text-primary);

            display: flex;
            align-items: center;
            justify-content: center;

            cursor: pointer;

            font-size: 17px;

            transition:
                background 0.15s,
                border-color 0.15s;
        }

        .topbar-sidebar-toggle:hover {
            background: rgba(128, 128, 128, 0.12);
            border-color: rgba(128, 128, 128, 0.6);
        }

        .sidebar .brand {
            font-size: 19px;
            font-weight: 700;
            padding: 0 24px 28px;
            border-bottom: 1px solid var(--sidebar-border);
            margin-bottom: 20px;
            color: #ffffff;
        }

        .sidebar nav {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--sidebar-text);
            text-decoration: none;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }

        .sidebar nav a:hover {
            background: var(--sidebar-hover);
        }

        .sidebar nav a.active {
            background: var(--sidebar-active);
            border-left-color: #ffffff;
            color: #ffffff;
        }

        .sidebar .icon {
            width: 18px;
            text-align: center;
        }

        .sidebar .logout {
            padding: 12px 24px;
            border-top: 1px solid var(--sidebar-border);
            margin-top: 12px;
        }

        .sidebar .logout button {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            padding: 10px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }

        .sidebar .logout button:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* ----- Main area ----- */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: var(--topbar-bg);
            padding: 18px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--topbar-shadow);
            transition: background 0.2s, box-shadow 0.2s;
        }

        .topbar h1 {
            font-size: 20px;
            margin: 0;
            color: var(--text-primary);
            transition: color 0.2s;
        }

        .topbar .user {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-primary);
            opacity: 0.85;
            transition: color 0.2s;
        }

        .topbar .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--avatar-bg);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s;
        }

        /* ----- dark mode toggle button (inside topbar) ----- */
        .theme-toggle {
            background: transparent;
            border: 1px solid rgba(128, 128, 128, 0.35);
            color: var(--text-primary);
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            margin-right: 6px;
        }

        .theme-toggle:hover {
            background: rgba(128, 128, 128, 0.12);
            border-color: rgba(128, 128, 128, 0.6);
        }

        .content {
            padding: 32px;
            flex: 1;
        }

        /* ----- optional card style to showcase dark mode ----- */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            transition: background 0.2s, box-shadow 0.2s;
            color: var(--text-primary);
        }

        .card h3 {
            margin-bottom: 8px;
            font-weight: 600;
        }

        .card p {
            opacity: 0.8;
            line-height: 1.5;
        }
       .payroll-menu {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .payroll-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            cursor: pointer;
        }

        .payroll-arrow {
            margin-left: auto;
            font-size: 12px;
            transition: transform 0.2s ease;
        }

        .payroll-submenu {
            display: none;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .payroll-submenu.show {
            display: block;
        }

        .payroll-submenu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px 10px 45px;
            text-decoration: none;
        }

        .payroll-arrow.rotate {
            transform: rotate(180deg);
        }


        body.payroll-show .content {
            padding: 20px;
        }
    </style>
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
<body class="{{ request()->routeIs('payroll.show', 'payroll.edit.show') ? 'payroll-show' : '' }}">
    <aside class="sidebar">

            <button type="button" class="sidebar-toggle" id="sidebarToggle">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            
    <div class="brand">
        Accounting Portal
    </div>

    <nav>

        <a href="{{ route('dashboard') }}"
           class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="icon">
                <i class="fa-solid fa-house"></i>
            </span>
            Dashboard
        </a>


        {{-- PAYROLL --}}
      <li class="nav-item payroll-menu">

            <a href="javascript:void(0);"
            class="payroll-toggle {{ request()->routeIs('payroll.*') ? 'active' : '' }}">

                <span class="icon">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </span>

                <span>Payroll</span>

                <i class="fa-solid fa-chevron-down payroll-arrow"></i>
            </a>

            <ul class="payroll-submenu">

                <li>
                    <a href="{{ route('payroll.create') }}"
                    class="{{ request()->routeIs('payroll.create') ? 'active' : '' }}">
                        <i class="fa-solid fa-plus"></i>
                        Create Payroll
                    </a>
                </li>

                <li>
                    <a href="{{ route('payroll.manage') }}"
                    class="{{ request()->routeIs('payroll.manage') ? 'active' : '' }}">
                        <i class="fa-solid fa-list"></i>
                        Manage Payroll
                    </a>
                </li>

            </ul>

        </li>
        
        <a href="#">
            <span class="icon">
                <i class="fa-solid fa-receipt"></i>
            </span>
            Expenses
        </a>

        <a href="#">
            <span class="icon">
                <i class="fa-solid fa-wallet"></i>
            </span>
            Budgets
        </a>

        <a href="#">
            <span class="icon">
                <i class="fa-solid fa-chart-line"></i>
            </span>
            Reports
        </a>

        <a href="#">
            <span class="icon">
                <i class="fa-solid fa-gear"></i>
            </span>
            Settings
        </a>

    </nav>


    <div class="logout">
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit">
                <i class="fa-solid fa-right-from-bracket"></i>
                Log out
            </button>
        </form>
    </div>

</aside>

    <div class="main">
       <header class="topbar">

    <h1>@yield('page-title', '')</h1>

    <div class="user">

        <button class="theme-toggle"
                id="themeToggle"
                aria-label="Toggle dark mode">
            🌙
        </button>

        <span>ID: {{ session('portal_idno') }}</span>

        <div class="avatar">
            {{ strtoupper(substr(session('portal_idno', 'U'), 0, 1)) }}
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

            // 2. Apply theme class and update button icon
            function applyTheme(theme) {
                if (theme === 'dark') {
                    html.classList.add('dark-mode');
                    toggleBtn.textContent = '☀️';   // sun icon for switching to light
                    toggleBtn.setAttribute('aria-label', 'Switch to light mode');
                } else {
                    html.classList.remove('dark-mode');
                    toggleBtn.textContent = '🌙';   // moon icon for switching to dark
                    toggleBtn.setAttribute('aria-label', 'Switch to dark mode');
                }
            }

            // 3. Set initial theme
            const initialTheme = getInitialTheme();
            applyTheme(initialTheme);

            // 4. Toggle on button click
            toggleBtn.addEventListener('click', function() {
                const isDark = html.classList.contains('dark-mode');
                const newTheme = isDark ? 'light' : 'dark';
                applyTheme(newTheme);
                localStorage.setItem('theme', newTheme);
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

            const payrollToggle = document.querySelector('.payroll-toggle');
            const payrollSubmenu = document.querySelector('.payroll-submenu');
            const payrollArrow = document.querySelector('.payroll-arrow');

            if (!payrollToggle || !payrollSubmenu) {
                return;
            }

            // If we are already inside a Payroll page,
            // keep the submenu open.
            const payrollIsActive = payrollToggle.classList.contains('active');

            if (payrollIsActive) {
                payrollSubmenu.classList.add('show');
                payrollArrow.classList.add('rotate');
            }

            // Click Payroll to open/close manually
            payrollToggle.addEventListener('click', function (e) {

                e.preventDefault();

                payrollSubmenu.classList.toggle('show');
                payrollArrow.classList.toggle('rotate');

            });

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const sidebarToggle = document.getElementById('sidebarToggle');

            if (!sidebarToggle) {
                return;
            }

            // Hide sidebar when entering Payroll Show
            if (document.body.classList.contains('payroll-show')) {
                document.body.classList.add('sidebar-collapsed');
            }

            // Arrow opens/closes sidebar
            sidebarToggle.addEventListener('click', function () {
                document.body.classList.toggle('sidebar-collapsed');
            });

        });
    </script>
</body>
</html>