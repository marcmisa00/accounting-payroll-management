<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Accounting Portal')</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            margin: 0;
            background: #f2f4f7;
            color: #222;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 240px;
            flex-shrink: 0;
            background: linear-gradient(180deg, #4b79a1, #283e51);
            color: #fff;
            display: flex;
            flex-direction: column;
            padding: 28px 0;
        }

        .sidebar .brand {
            font-size: 19px;
            font-weight: 700;
            padding: 0 24px 28px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 20px;
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
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 500;
            border-left: 3px solid transparent;
        }

        .sidebar nav a:hover {
            background: rgba(255,255,255,0.08);
        }

        .sidebar nav a.active {
            background: rgba(255,255,255,0.12);
            border-left-color: #ffffff;
            color: #fff;
        }

        .sidebar .icon {
            width: 18px;
            text-align: center;
        }

        .sidebar .logout {
            padding: 12px 24px;
            border-top: 1px solid rgba(255,255,255,0.15);
            margin-top: 12px;
        }

        .sidebar .logout button {
            width: 100%;
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            padding: 10px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .sidebar .logout button:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Main area */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: #fff;
            padding: 18px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .topbar h1 {
            font-size: 20px;
            margin: 0;
            color: #222;
        }

        .topbar .user {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #555;
        }

        .topbar .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #4b79a1;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .content {
            padding: 32px;
            flex: 1;
        }
    </style>

    @yield('styles')
</head>
<body>

    <aside class="sidebar">
        <div class="brand">Accounting Portal</div>
        <nav>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="icon">&#9632;</span> Dashboard
            </a>
            <a href="#"><span class="icon">&#9776;</span> Invoices</a>
            <a href="#"><span class="icon">&#9679;</span> Expenses</a>
            <a href="#"><span class="icon">&#9673;</span> Budgets</a>
            <a href="#"><span class="icon">&#9636;</span> Reports</a>
            <a href="#"><span class="icon">&#9881;</span> Settings</a>
        </nav>
        <div class="logout">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Log out</button>
            </form>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <h1>@yield('page-title', 'Dashboard')</h1>
            <div class="user">
                <span>ID: {{ session('portal_idno') }}</span>
                <div class="avatar">{{ strtoupper(substr(session('portal_idno', 'U'), 0, 1)) }}</div>
            </div>
        </header>

        <div class="content">
            @yield('content')
        </div>
    </div>

</body>
</html>