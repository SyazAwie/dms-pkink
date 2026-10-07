<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DMS PKINK - @yield('title', 'Papan Pemuka')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --dms-brand: #183f68;
            --dms-primary: #1769d2;
            --dms-primary-hover: #1159b5;
            --dms-mint: #25a979;
            --dms-page-start: #f1f6fb;
            --dms-page-middle: #edf8fb;
            --dms-page-end: #e8f0fb;
            --dms-surface: rgba(255, 255, 255, .72);
            --dms-surface-strong: rgba(255, 255, 255, .9);
            --dms-border: rgba(255, 255, 255, .8);
            --dms-line: #dce5ef;
            --dms-text: #1c3048;
            --dms-muted: #68798d;
            --dms-danger: #c43d4e;
            --dms-shadow: 0 18px 48px rgba(24, 63, 104, .1);
            --sidebar-width: 280px;
        }

        * { box-sizing: border-box; }

        html { min-width: 320px; }

        body {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
            color: var(--dms-text);
            background:
                radial-gradient(circle at 0 0, rgba(23, 105, 210, .12), transparent 30rem),
                radial-gradient(circle at 100% 100%, rgba(37, 169, 121, .1), transparent 34rem),
                linear-gradient(135deg, var(--dms-page-start), var(--dms-page-middle) 50%, var(--dms-page-end));
            font-family: "Plus Jakarta Sans", sans-serif;
        }

        button, a { -webkit-tap-highlight-color: transparent; }
        a { text-decoration: none; }

        .app-shell { min-height: 100vh; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1040;
            display: flex;
            width: var(--sidebar-width);
            flex-direction: column;
            border-right: 1px solid var(--dms-border);
            background: rgba(255, 255, 255, .76);
            box-shadow: var(--dms-shadow);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            transition: transform .3s ease;
        }

        .brand {
            display: grid;
            min-height: 80px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--dms-line);
        }

        .brand-mark {
            display: grid;
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 12px;
            color: #fff;
            background: linear-gradient(135deg, var(--dms-brand), var(--dms-primary));
            box-shadow: 0 8px 22px rgba(23, 105, 210, .24);
            font-size: 20px;
        }

        .brand-copy { min-width: 0; line-height: 1.25; }
        .brand-title { overflow: hidden; color: var(--dms-brand); font-size: 16px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .brand-subtitle { overflow: hidden; margin-top: 4px; color: var(--dms-muted); font-size: 11px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }

        .icon-button {
            display: inline-grid;
            width: 40px;
            height: 40px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid var(--dms-line);
            border-radius: 10px;
            color: var(--dms-text);
            background: rgba(255, 255, 255, .72);
            cursor: pointer;
            transition: border-color .2s ease, background .2s ease, color .2s ease, transform .2s ease;
        }

        .icon-button:hover { border-color: rgba(23, 105, 210, .35); color: var(--dms-primary); background: #fff; }
        .icon-button:active { transform: scale(.97); }
        .icon-button:focus-visible, .nav-link:focus-visible, .quick-link:focus-visible { outline: 3px solid rgba(23, 105, 210, .2); outline-offset: 2px; }
        .sidebar-close { display: none; }

        .nav-area { flex: 1; overflow-y: auto; padding: 18px 16px; }
        .nav-label { margin: 0 12px 10px; color: var(--dms-muted); font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .nav-list { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }

        .nav-link {
            display: grid;
            min-height: 46px;
            grid-template-columns: 22px minmax(0, 1fr) auto;
            align-items: center;
            gap: 11px;
            padding: 11px 13px;
            border-radius: 12px;
            color: var(--dms-muted);
            font-size: 13px;
            font-weight: 700;
            transition: color .2s ease, background .2s ease, transform .2s ease, box-shadow .2s ease;
        }

        .nav-link i:first-child { font-size: 18px; text-align: center; }
        .nav-link span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .nav-link:hover { color: var(--dms-text); background: rgba(231, 239, 248, .9); transform: translateX(2px); }
        .nav-link.active { color: #fff; background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); box-shadow: 0 8px 20px rgba(23, 105, 210, .2); }

        .sidebar-footer { padding: 15px 16px; border-top: 1px solid var(--dms-line); }
        .sidebar-user { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 11px; padding: 11px; border-radius: 12px; background: rgba(231, 239, 248, .72); }
        .avatar { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 10px; color: var(--dms-brand); background: rgba(37, 169, 121, .13); font-size: 13px; font-weight: 800; }
        .user-copy { min-width: 0; }
        .user-name { overflow: hidden; color: var(--dms-text); font-size: 12px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .user-role { margin-top: 3px; color: var(--dms-muted); font-size: 10px; }

        .page { min-height: 100vh; padding-left: var(--sidebar-width); }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            display: grid;
            min-height: 80px;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 16px;
            padding: 12px 32px;
            border-bottom: 1px solid var(--dms-border);
            background: rgba(255, 255, 255, .72);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .topbar-left { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 12px; min-width: 0; }
        .menu-toggle { display: none; }
        .page-heading { min-width: 0; }
        .page-eyebrow { overflow: hidden; color: var(--dms-primary); font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .page-title { overflow: hidden; margin: 3px 0 0; color: var(--dms-brand); font-size: 20px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }

        .topbar-actions { display: flex; align-items: center; gap: 9px; }
        .notification { position: relative; }
        .notification::after { position: absolute; top: 8px; right: 8px; width: 7px; height: 7px; border: 2px solid #fff; border-radius: 50%; background: var(--dms-danger); content: ""; }
        .top-user { min-width: 0; max-width: 200px; text-align: right; }
        .top-user strong, .top-user small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .top-user strong { font-size: 12px; }
        .top-user small { margin-top: 2px; color: var(--dms-muted); font-size: 10px; }
        .logout-form { margin: 0; }

        .content-wrap { width: 100%; max-width: 1500px; margin: 0 auto; padding: 28px 32px 40px; }
        .content-panel { min-height: calc(100vh - 148px); }

        .glass-panel {
            border: 1px solid var(--dms-border);
            border-radius: 12px;
            background: var(--dms-surface);
            box-shadow: 0 12px 34px rgba(24, 63, 104, .07);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .page-intro { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 18px; margin-bottom: 24px; }
        .page-intro h2 { margin: 0; color: var(--dms-brand); font-size: 24px; font-weight: 800; }
        .page-intro p { margin: 7px 0 0; color: var(--dms-muted); font-size: 13px; line-height: 1.7; }
        .date-chip { display: inline-flex; align-items: center; gap: 8px; padding: 9px 12px; border: 1px solid var(--dms-line); border-radius: 9px; color: var(--dms-muted); background: var(--dms-surface-strong); font-size: 11px; font-weight: 700; }
        .date-chip i { color: var(--dms-primary); }

        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .summary-card { padding: 20px; transition: transform .2s ease, box-shadow .2s ease; }
        .summary-card:hover { transform: translateY(-3px); box-shadow: var(--dms-shadow); }
        .summary-head { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 14px; }
        .summary-label { overflow: hidden; color: var(--dms-muted); font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .summary-value { margin-top: 7px; color: var(--dms-brand); font-size: 29px; font-weight: 800; }
        .summary-icon { display: grid; width: 43px; height: 43px; place-items: center; border-radius: 11px; color: var(--dms-primary); background: rgba(23, 105, 210, .1); font-size: 19px; }
        .summary-detail { margin-top: 15px; color: var(--dms-muted); font-size: 10px; font-weight: 600; }

        .dashboard-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(280px, .8fr); gap: 20px; margin-top: 20px; }
        .section-card { padding: 22px; }
        .section-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 14px; padding-bottom: 15px; border-bottom: 1px solid var(--dms-line); }
        .section-header h3 { overflow: hidden; margin: 0; color: var(--dms-brand); font-size: 15px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .section-header p { margin: 5px 0 0; color: var(--dms-muted); font-size: 10px; }
        .outline-button { display: inline-flex; min-height: 35px; align-items: center; justify-content: center; gap: 7px; padding: 7px 12px; border: 1px solid var(--dms-line); border-radius: 8px; color: var(--dms-text); background: rgba(255, 255, 255, .72); font-size: 10px; font-weight: 700; }
        .outline-button:hover { border-color: rgba(23, 105, 210, .35); color: var(--dms-primary); background: #fff; }

        .document-list { margin: 0; padding: 0; list-style: none; }
        .document-item { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--dms-line); }
        .document-item:last-child { border-bottom: 0; padding-bottom: 0; }
        .document-icon { display: grid; width: 40px; height: 40px; place-items: center; border-radius: 9px; color: var(--dms-primary); background: rgba(23, 105, 210, .1); }
        .document-copy { min-width: 0; }
        .document-name, .document-meta { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .document-name { font-size: 12px; font-weight: 800; }
        .document-meta { margin-top: 4px; color: var(--dms-muted); font-size: 10px; }
        .document-time { color: var(--dms-muted); font-size: 9px; font-weight: 600; }

        .quick-title { margin: 0; color: var(--dms-brand); font-size: 15px; font-weight: 800; }
        .quick-subtitle { margin: 5px 0 16px; color: var(--dms-muted); font-size: 10px; }
        .quick-list { display: grid; gap: 10px; }
        .quick-link { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 11px; padding: 11px; border: 1px solid var(--dms-line); border-radius: 10px; color: var(--dms-text); background: rgba(255, 255, 255, .55); transition: border-color .2s ease, background .2s ease, transform .2s ease; }
        .quick-link:hover { border-color: rgba(23, 105, 210, .35); color: var(--dms-primary); background: rgba(23, 105, 210, .05); transform: translateX(2px); }
        .quick-icon { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 8px; color: var(--dms-primary); background: rgba(23, 105, 210, .1); }
        .quick-link span { overflow: hidden; font-size: 11px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }

        .sidebar-backdrop { position: fixed; inset: 0; z-index: 1030; display: none; border: 0; background: rgba(24, 63, 104, .38); backdrop-filter: blur(3px); }

        @media (max-width: 1199.98px) {
            .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.is-open { transform: translateX(0); }
            .sidebar-backdrop.is-visible { display: block; }
            .sidebar-close, .menu-toggle { display: inline-grid; }
            .page { padding-left: 0; }
            .topbar { padding-inline: 20px; }
            .content-wrap { padding-inline: 20px; }
        }

        @media (max-width: 575.98px) {
            .sidebar { width: min(86vw, 300px); }
            .topbar { min-height: 72px; grid-template-columns: minmax(0, 1fr) auto; padding: 10px 14px; }
            .topbar-left { min-width: 0; }
            .top-user { display: none; }
            .notification { display: none; }
            .page-title { font-size: 17px; }
            .content-wrap { padding: 22px 14px 32px; }
            .content-panel { min-height: calc(100vh - 126px); }
            .page-intro { grid-template-columns: 1fr; }
            .page-intro h2 { font-size: 21px; }
            .date-chip { display: none; }
            .summary-grid { grid-template-columns: 1fr; }
            .section-card { padding: 17px; }
            .section-header { align-items: start; }
            .outline-button { width: 36px; padding: 0; }
            .outline-button span { display: none; }
            .document-time { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; }
        }
    </style>

    @stack('styles')
</head>
<body>
    <div class="app-shell">
        <button type="button" class="sidebar-backdrop" id="sidebarBackdrop" aria-label="Tutup menu navigasi"></button>

        <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
            <div class="brand">
                <img src="{{ asset('images/Logo_PKINK_Tulisan_Jawi_Bawah.png') }}" alt="Logo PKINK" style="width: 44px; height: 44px; object-fit: contain; flex: 0 0 auto;">
                <div class="brand-copy">
                    <div class="brand-title">DMS PKINK</div>
                    <div class="brand-subtitle">Sistem Arkib Digital</div>
                </div>
                <button type="button" class="icon-button sidebar-close" id="sidebarClose" aria-label="Tutup menu">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>

            <nav class="nav-area">
                <p class="nav-label">Menu Utama</p>
                <ul class="nav-list">
                    <li>
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
                            <span>Papan Pemuka</span>
                            @if(request()->routeIs('dashboard'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN'))
                    <li>
                        <a href="{{ route('jenis-dokumen.index') }}" class="nav-link {{ request()->routeIs('jenis-dokumen.*') ? 'active' : '' }}">
                            <i class="bi bi-folder2-open" aria-hidden="true"></i>
                            <span>Kategori Dokumen</span>
                            @if(request()->routeIs('jenis-dokumen.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->can('viewAny', \App\Models\Dokumen::class))
                    <li>
                        <a href="{{ route('dokumen.index') }}" class="nav-link {{ request()->routeIs('dokumen.*') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i>
                            <span>Dokumen</span>
                            @if(request()->routeIs('dokumen.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @can('create', \App\Models\Dokumen::class)
                    <li>
                        <a href="{{ route('imbas-arkib.create') }}" class="nav-link {{ request()->routeIs('imbas-arkib.*') ? 'active' : '' }}">
                            <i class="bi bi-magic" aria-hidden="true"></i>
                            <span>Imbas &amp; Arkib</span>
                            @if(request()->routeIs('imbas-arkib.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endcan
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN'))
                    <li>
                        <a href="{{ route('bahagian.index') }}" class="nav-link {{ request()->routeIs('bahagian.*') ? 'active' : '' }}">
                            <i class="bi bi-building" aria-hidden="true"></i>
                            <span>Pengurusan Bahagian</span>
                            @if(request()->routeIs('bahagian.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN'))
                    <li>
                        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <i class="bi bi-people-fill" aria-hidden="true"></i>
                            <span>Pengurusan Kakitangan</span>
                            @if(request()->routeIs('users.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'AUDIT'))
                    <li>
                        <a href="{{ route('log-audit.index') }}" class="nav-link {{ request()->routeIs('log-audit.*') ? 'active' : '' }}">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            <span>Log Audit</span>
                            @if(request()->routeIs('log-audit.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN', 'PENYOKONG', 'PELULUS'))
                    <li>
                        <a href="{{ route('kelulusan.index') }}" class="nav-link {{ request()->routeIs('kelulusan.*') ? 'active' : '' }}">
                            <i class="bi bi-patch-check" aria-hidden="true"></i>
                            <span>Kelulusan</span>
                            @if(request()->routeIs('kelulusan.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="avatar" aria-hidden="true">
                        {{ strtoupper(substr(Auth::user()->nama_staff, 0, 1)) }}
                    </div>
                    <div class="user-copy">
                        <div class="user-name">{{ Auth::user()->nama_staff }}</div>
                        @php $perananPengguna = Auth::user()->roles->pluck('nama_roles'); @endphp
                        <div class="user-role">{{ $perananPengguna->first() ?? 'Tiada peranan' }}{{ $perananPengguna->count() > 1 ? ' +' . ($perananPengguna->count() - 1) : '' }}</div>
                    </div>
                </div>
            </div>
        </aside>

        <div class="page">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="icon-button menu-toggle" id="menuToggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <div class="page-heading">
                        <div class="page-eyebrow">Sistem Arkib Digital</div>
                        <h1 class="page-title">@yield('title', 'Papan Pemuka')</h1>
                    </div>
                </div>

                <div class="topbar-actions">
                    <button type="button" class="icon-button notification" aria-label="Pemberitahuan" title="Pemberitahuan">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                    </button>
                    <div class="top-user">
                        <strong>{{ Auth::user()->nama_staff }}</strong>
                        <small>{{ $perananPengguna->first() ?? 'Tiada peranan' }}{{ $perananPengguna->count() > 1 ? ' +' . ($perananPengguna->count() - 1) : '' }}</small>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="logout-form">
                        @csrf
                        <button type="submit" class="icon-button" aria-label="Log keluar" title="Log keluar">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </header>

            <div class="content-wrap">
                <main class="content-panel">
                    @yield('content')
                </main>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (() => {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('menuToggle');
            const closeButton = document.getElementById('sidebarClose');
            const backdrop = document.getElementById('sidebarBackdrop');

            if (!sidebar || !toggle || !closeButton || !backdrop) return;

            const setMenu = (open) => {
                sidebar.classList.toggle('is-open', open);
                backdrop.classList.toggle('is-visible', open);
                toggle.setAttribute('aria-expanded', String(open));
                document.body.style.overflow = open ? 'hidden' : '';
            };

            toggle.addEventListener('click', () => setMenu(true));
            closeButton.addEventListener('click', () => setMenu(false));
            backdrop.addEventListener('click', () => setMenu(false));

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setMenu(false);
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 992) setMenu(false);
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>