<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Employee Portal')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --primary-light: #6366f1;
            --sidebar-text: #374151;
            --bg: #eef1fb;
            --card: rgba(255,255,255,.55);
            --glass-border: rgba(255,255,255,.6);
            --text: #1f2937;
            --muted: #6b7280;
            --border: rgba(17,24,39,.08);
            --success: #16a34a;
            --danger: #dc2626;
            --sidebar-width: 240px;
            --topbar-height: 72px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #fdf2ff 45%, #e0f2fe 100%);
            background-attachment: fixed;
            color: var(--text);
        }

        /* ===== Guest (login) shell ===== */
        .guest-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        /* ===== App shell (authenticated) ===== */
        .app-shell { display: flex; min-height: 100vh; }

        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255,255,255,.45);
            backdrop-filter: blur(20px) saturate(160%);
            -webkit-backdrop-filter: blur(20px) saturate(160%);
            border-right: 1px solid var(--glass-border);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 40;
            transition: transform .25s ease;
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            height: var(--topbar-height);
            padding: 0 18px;
            font-weight: 800;
            font-size: 16px;
            color: var(--text);
            border-bottom: 1px solid rgba(17,24,39,.08);
        }
        .sidebar-brand .sidebar-logo {
            width: 34px; height: 34px;
            border-radius: 9px;
            object-fit: contain;
            background: #fff;
            padding: 3px;
            flex-shrink: 0;
        }
        .sidebar-brand span:last-child {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sidebar-nav { flex: 1; padding: 14px 10px; overflow-y: auto; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 12px;
            border-radius: 10px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
            transition: background .15s ease, color .15s ease;
        }
        .sidebar-nav a .nav-icon { font-size: 17px; width: 20px; text-align: center; }
        .sidebar-nav a:hover { background: rgba(17,24,39,.06); color: var(--primary-dark); }
        .sidebar-nav a.active { background: linear-gradient(135deg, var(--primary-light), var(--primary)); color: #fff; box-shadow: 0 4px 12px rgba(79,70,229,.35); }

        .sidebar-footer {
            padding: 14px 16px 18px;
            border-top: 1px solid rgba(17,24,39,.08);
        }
        .sidebar-user { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .sidebar-user .avatar {
            width: 36px; height: 36px; border-radius: 50%;
            object-fit: cover; background: rgba(17,24,39,.06);
        }
        .sidebar-user .name { font-size: 13px; font-weight: 700; color: var(--text); line-height: 1.2; }
        .sidebar-user .eid { font-size: 11px; color: var(--muted); }
        .sidebar-footer form button {
            width: 100%;
            background: rgba(17,24,39,.06);
            color: var(--text);
            border: none;
            border-radius: 10px;
            padding: 9px 0;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .sidebar-footer form button:hover { background: rgba(17,24,39,.12); }

        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 39;
        }
        .sidebar-overlay.show { display: block; }

        .main-area {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-width: 0;
        }

        .topbar {
            background: rgba(255,255,255,.45);
            backdrop-filter: blur(16px) saturate(160%);
            -webkit-backdrop-filter: blur(16px) saturate(160%);
            border-bottom: 1px solid var(--glass-border);
            height: var(--topbar-height);
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .hamburger {
            display: none;
            background: rgba(255,255,255,.5);
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 36px; height: 36px;
            font-size: 16px;
            cursor: pointer;
            color: var(--text);
        }
        .page-title { font-weight: 800; font-size: 17px; flex: 1; }

        .content { padding: 24px 28px; width: 100%; }

        .content-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .content-grid { grid-template-columns: 1fr; }
        }

        .card {
            background: var(--card);
            backdrop-filter: blur(18px) saturate(160%);
            -webkit-backdrop-filter: blur(18px) saturate(160%);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 24px rgba(31,41,55,.06);
            border: 1px solid var(--glass-border);
        }
        .card-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 14px;
        }
        .card-title a { font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 700; }
        .btn {
            display: inline-block;
            border: none;
            border-radius: 10px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-block { width: 100%; }
        .form-group { margin-bottom: 12px; }
        .form-label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 4px; font-weight: 600; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid rgba(17,24,39,.12);
            border-radius: 10px;
            font-size: 14px;
            background: rgba(255,255,255,.6);
            color: var(--text);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            background: rgba(255,255,255,.85);
        }
        select.form-control { background: rgba(255,255,255,.6); }
        .password-field-wrap { position: relative; }
        .password-field-wrap .form-control { padding-right: 40px; }
        .password-toggle {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 16px;
            color: var(--muted);
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table th, table td { text-align: left; padding: 12px 10px; border-bottom: 1px solid var(--border); }
        table th { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .4px; font-weight: 700; }
        table tbody tr { transition: background .12s ease; }
        table tbody tr:hover { background: #fafaff; }
        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr.row-today { background: #eef2ff; }
        table tbody tr.row-today td:first-child { font-weight: 700; color: var(--primary); }
        .table-responsive-wrap { overflow-x: auto; }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-tile {
            background: rgba(255,255,255,.55);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 2px 10px rgba(31,41,55,.05);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .stat-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(31,41,55,.09); }
        .stat-tile-icon {
            width: 40px; height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            background: rgba(79,70,229,.12);
            color: var(--primary);
        }
        .stat-tile.accent-success .stat-tile-icon { background: rgba(22,163,74,.14); color: var(--success); }
        .stat-tile.accent-warning .stat-tile-icon { background: rgba(180,83,9,.14); color: #b45309; }
        .stat-tile.accent-danger .stat-tile-icon { background: rgba(220,38,38,.12); color: var(--danger); }
        .stat-tile .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 2px;
        }
        .stat-tile .stat-value { font-size: 20px; font-weight: 800; color: var(--text); line-height: 1.2; }
        .stat-tile.accent-primary .stat-value { color: var(--primary); }
        .stat-tile.accent-success .stat-value { color: var(--success); }
        .stat-tile.accent-warning .stat-value { color: #b45309; }
        .stat-tile.accent-danger .stat-value { color: var(--danger); }
        @media (max-width: 560px) {
            .stat-grid { grid-template-columns: 1fr 1fr; }
        }
        .empty-state {
            text-align: center;
            padding: 40px 16px;
            color: var(--muted);
        }
        .empty-state .empty-icon { font-size: 34px; margin-bottom: 8px; opacity: .6; }
        .empty-state .empty-text { font-size: 13px; }

        .dashboard-greeting-card {
            background: linear-gradient(135deg, rgba(199,210,254,.5), rgba(255,255,255,.5));
        }
        .dashboard-avatar {
            width: 60px; height: 60px;
            object-fit: cover;
            border-radius: 14px;
            border: 2px solid rgba(255,255,255,.8);
            box-shadow: 0 0 0 2px var(--primary-light);
        }
        .attendance-time-row {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
        }
        .attendance-time-box {
            flex: 1;
            background: var(--bg);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
        }
        .attendance-time-value { font-size: 20px; font-weight: 800; color: var(--muted); margin-top: 4px; }
        .attendance-time-value.text-success { color: var(--success); }

        .btn-punch {
            font-size: 15px;
            padding: 14px 0;
            border-radius: 12px;
            letter-spacing: .3px;
        }
        .btn-punch-in { background: var(--danger); color: #fff; }
        .btn-punch-in:hover { background: #b91c1c; }
        .btn-punch-out { background: var(--primary); color: #fff; }
        .btn-punch-out:hover { background: var(--primary-dark); }
        .btn-punch-done { background: #dcfce7; color: var(--success); cursor: default; }

        .profile-header-card {
            background: linear-gradient(135deg, rgba(199,210,254,.5), rgba(255,255,255,.5));
            text-align: center;
        }
        .profile-header { color: var(--text); }
        .profile-avatar-wrap { position: relative; display: inline-block; }
        .profile-avatar {
            width: 92px; height: 92px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        .profile-avatar-badge {
            position: absolute;
            bottom: 2px; right: 2px;
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            border: 3px solid #fff;
        }
        .profile-badge-row { display: flex; justify-content: center; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .profile-badge-row .badge { background: #fff; border: 1px solid var(--border); color: var(--text); font-weight: 600; }
        .profile-badge-row .badge i { color: var(--primary); margin-right: 4px; }

        .detail-list .detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .detail-list .detail-row:last-child { border-bottom: none; }
        .detail-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: #eef2ff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .detail-text .detail-label { font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: var(--muted); font-weight: 700; }
        .detail-text .detail-value { font-size: 14px; font-weight: 600; color: var(--text); margin-top: 2px; }

        .quick-action-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 12px 14px;
            border-radius: 10px;
            background: var(--bg);
            color: var(--text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
            transition: background .15s ease;
        }
        .quick-action-link:last-child { margin-bottom: 0; }
        .quick-action-link:hover { background: #eef2ff; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px 20px;
        }
        .info-item .info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 4px;
        }
        .info-item .info-value { font-size: 14px; font-weight: 600; color: var(--text); }

        .leave-progress-row { margin-bottom: 16px; }
        .leave-progress-row:last-child { margin-bottom: 0; }
        .leave-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text);
        }
        .leave-progress-label span:last-child { color: var(--muted); font-weight: 500; }
        .leave-progress-track {
            background: rgba(17,24,39,.08);
            border-radius: 20px;
            height: 10px;
            overflow: hidden;
        }
        .leave-progress-fill {
            height: 100%;
            min-width: 4px;
            border-radius: 20px;
            background: linear-gradient(90deg, var(--primary-light), var(--primary));
            transition: width .3s ease;
        }

        .week-strip {
            display: flex;
            justify-content: space-between;
            gap: 6px;
        }
        .week-day { text-align: center; flex: 1; }
        .week-day-label { font-size: 11px; color: var(--muted); font-weight: 700; margin-bottom: 8px; text-transform: uppercase; }
        .week-day-today .week-day-label { color: var(--primary); }
        .week-day-dot {
            width: 36px; height: 36px;
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            background: rgba(17,24,39,.06);
            color: var(--muted);
        }
        .week-day-present { background: rgba(22,163,74,.15); color: var(--success); }
        .week-day-late { background: rgba(180,83,9,.15); color: #b45309; }
        .week-day-absent { background: rgba(220,38,38,.12); color: var(--danger); }
        .week-day-weekend { background: rgba(79,70,229,.1); color: var(--primary); }
        .week-day-today .week-day-dot { box-shadow: 0 0 0 2px var(--primary-light); }

        .conveyance-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .conveyance-row:last-child { border-bottom: none; padding-bottom: 0; }

        .notice-item { padding: 14px 0; border-bottom: 1px solid var(--border); }
        .notice-item:last-child { border-bottom: none; padding-bottom: 0; }
        .notice-item-header { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-bottom: 6px; }
        .notice-item-title { font-weight: 700; font-size: 14px; }
        .notice-item-date { font-size: 11px; color: var(--muted); white-space: nowrap; }
        .notice-item-body { font-size: 13px; color: var(--text); line-height: 1.5; white-space: pre-line; }

        .latest-notice-card { border-left: 4px solid var(--primary); }
        .latest-notice-title { font-weight: 700; font-size: 14px; margin-bottom: 4px; }
        .latest-notice-body { font-size: 13px; color: var(--muted); }
        .latest-notice-date { font-size: 11px; color: var(--muted); margin-top: 6px; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .badge-success { background: #dcfce7; color: var(--success); }
        .badge-danger { background: #fee2e2; color: var(--danger); }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-muted { background: #f3f4f6; color: var(--muted); }
        .alert { padding: 10px 14px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .modal-backdrop {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.5);
            z-index: 50;
            align-items: center;
            justify-content: center;
        }
        .modal-backdrop.show { display: flex; }
        .modal-sheet {
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(20px) saturate(160%);
            -webkit-backdrop-filter: blur(20px) saturate(160%);
            width: 100%;
            max-width: 460px;
            border-radius: 16px;
            padding: 22px;
            max-height: 88vh;
            overflow-y: auto;
            margin: 16px;
            border: 1px solid var(--glass-border);
        }
        .modal-sheet .modal-title { font-weight: 700; font-size: 16px; margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center; }
        .close-link { color: var(--muted); text-decoration: none; font-size: 22px; line-height: 1; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-area { margin-left: 0; }
            .hamburger { display: inline-flex; align-items: center; justify-content: center; }
            .content { padding: 16px; }
            .modal-sheet { border-radius: 16px 16px 0 0; margin: 0; max-width: 100%; align-self: flex-end; }
            .modal-backdrop { align-items: flex-end; }
        }
    </style>
</head>
<body>
    @auth('employee')
        @php $employee = Auth::guard('employee')->user()->employee; @endphp
        <div class="app-shell">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-brand">
                    <img src="{{ asset(general()->logo()) }}" alt="logo" class="sidebar-logo">
                    <span>Employee Portal</span>
                </div>
                <nav class="sidebar-nav">
                    <a href="{{ route('employee-portal.dashboard') }}" class="{{ request()->routeIs('employee-portal.dashboard') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span> Home
                    </a>
                    <a href="{{ route('employee-portal.attendance.index') }}" class="{{ request()->routeIs('employee-portal.attendance.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-clock"></i></span> Attendance
                    </a>
                    <a href="{{ route('employee-portal.leave.index') }}" class="{{ request()->routeIs('employee-portal.leave.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span> Leave
                    </a>
                    <a href="{{ route('employee-portal.conveyance.index') }}" class="{{ request()->routeIs('employee-portal.conveyance.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-route"></i></span> Conveyance
                    </a>
                    <a href="{{ route('employee-portal.notices.index') }}" class="{{ request()->routeIs('employee-portal.notices.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span> Notices
                    </a>
                    <a href="{{ route('employee-portal.profile') }}" class="{{ request()->routeIs('employee-portal.profile') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span> Profile
                    </a>
                </nav>
                <div class="sidebar-footer">
                    <div class="sidebar-user">
                        <img class="avatar" src="{{ asset($employee->image('sm')) }}" alt="photo">
                        <div>
                            <div class="name">{{ $employee->name }}</div>
                            <div class="eid">{{ $employee->employee_id }}</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('employee-portal.logout') }}">
                        @csrf
                        <button type="submit">&#10148; Logout</button>
                    </form>
                </div>
            </aside>
            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <div class="main-area">
                <header class="topbar">
                    <button type="button" class="hamburger" id="hamburgerBtn">&#9776;</button>
                    <div class="page-title">@yield('page-title', 'Dashboard')</div>
                </header>
                <main class="content">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-error">{{ session('error') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-error">{{ $errors->first() }}</div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <div class="guest-wrap">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </div>
    @endauth

    <script>
        function togglePasswordVisibility(fieldId, btn) {
            var field = document.getElementById(fieldId);
            var isHidden = field.type === 'password';
            field.type = isHidden ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = isHidden ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            }
        }

        (function () {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var hamburger = document.getElementById('hamburgerBtn');
            if (!sidebar || !hamburger) return;

            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('show');
            }
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            }

            hamburger.addEventListener('click', openSidebar);
            overlay.addEventListener('click', closeSidebar);
            sidebar.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', closeSidebar);
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
